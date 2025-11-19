<?php

declare(strict_types=1);

namespace DLme\Core;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service for creating and managing CallRequest entities.
 *
 * Responsibilities:
 * - Create CallRequest from CheckoutSession after payment
 * - Reschedule requests (capture for later)
 * - Mark requests as expired
 * - Generate request IDs
 * - Apply product metadata (pricing, timing)
 *
 * This service orchestrates the creation and lifecycle of call requests,
 * but does NOT handle execution or completion (see CallExecutionService and CallCompletionService).
 */
final class CallRequestService
{
    public function __construct(
        private readonly CallRequestRepository $repository,
        private readonly ConsultantPresenceRepository $presenceRepository,
        private readonly ProductMetadataProvider $productMetadata,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Create CallRequest from successful CheckoutSession.
     *
     * Applies product metadata (pricing model, duration, initiation window)
     * and creates initial request in PENDING status.
     *
     * @param CheckoutSession $session Successful checkout session
     * @return CallRequest New call request
     */
    public function createFromCheckoutSession(CheckoutSession $session): CallRequest
    {
        $sku = $session->sku ?? '';
        $now = new DateTimeImmutable();

        // Get product metadata
        $pricingModel = $this->productMetadata->getPricingModel($sku);
        $callDurationMinutes = $this->productMetadata->getCallDurationMinutes($sku);
        $initiationWindowMinutes = $this->productMetadata->getInitiationWindowMinutes($sku);
        $prepaidMinutes = $this->productMetadata->getPrepaidMinutes($sku);

        // Calculate initiation window
        $initiationWindowStart = $now;
        $initiationWindowEnd = $now->modify("+{$initiationWindowMinutes} minutes");

        $request = new CallRequest(
            id: $this->generateId(),
            checkoutSessionId: $session->id,
            sellerId: $session->sellerId,
            buyerId: $session->buyerId ?? 0,
            buyerPhone: $session->buyerPhone ?? '',
            buyerEmail: $session->buyerEmail,
            productId: $session->productId ?? 0,
            sku: $sku,
            pricingModel: $pricingModel,
            agreedPrice: number_format($session->quotedPrice ?? 0.0, 2, '.', ''),
            currency: 'USD',
            prepaidMinutes: $prepaidMinutes,
            createdAt: $now,
            initiationWindowStart: $initiationWindowStart,
            initiationWindowEnd: $initiationWindowEnd,
            callDurationMinutes: $callDurationMinutes,
            scheduledExecutionTime: null,
            status: CallRequestStatus::PENDING,
            actualCallStartTime: null,
            actualCallEndTime: null,
            actualCallDurationMinutes: null,
            callCompletedSuccessfully: false,
            correlationId: $session->correlationId ?? '',
            referrerUrl: $session->referrerUrl,
            supersededByRequestId: null,
            supersededRequestId: null
        );

        $this->repository->save($request);

        $this->logger->info('dlme.call_request.created', $this->buildContext([
            'call_request_id' => $request->id,
            'checkout_session_id' => $request->checkoutSessionId,
            'seller_id' => $request->sellerId,
            'buyer_id' => $request->buyerId,
            'sku' => $request->sku,
            'pricing_model' => $request->pricingModel->value,
            'agreed_price' => $request->agreedPrice,
            'call_duration_minutes' => $request->callDurationMinutes,
            'initiation_window_end' => $request->initiationWindowEnd->format('c'),
        ]));

        return $request;
    }

    /**
     * Reschedule a call request (capture for later).
     *
     * Creates a new CallRequest superseding the original:
     * - Original marked as SUPERSEDED
     * - New request created with new scheduled time
     * - Links back to same CheckoutSession
     * - Same pricing and product details
     *
     * @param string $originalRequestId Original request ID
     * @param int $delayMinutes Delay in minutes before execution
     * @param int|null $postSessionBufferMinutes Optional buffer after current session
     * @return CallRequest New rescheduled request
     */
    public function reschedule(
        string $originalRequestId,
        int $delayMinutes,
        ?int $postSessionBufferMinutes = null
    ): CallRequest {
        if ($delayMinutes <= 0) {
            throw new DomainException('Delay minutes must be greater than zero.');
        }

        $original = $this->repository->findById($originalRequestId);

        if ($original === null) {
            throw new \RuntimeException("CallRequest not found: {$originalRequestId}");
        }

        if ($original->status !== CallRequestStatus::PENDING
            && $original->status !== CallRequestStatus::SCHEDULED) {
            throw new DomainException(
                sprintf(
                    'Cannot reschedule request in %s status.',
                    $original->status->value
                )
            );
        }

        $now = new DateTimeImmutable();

        // Calculate scheduled execution time
        if ($postSessionBufferMinutes !== null) {
            // "After current session" - need to look at presence for estimated end time
            $presence = $this->presenceRepository->getPresence($original->sellerId);

            if ($presence === null || $presence->estimatedSessionEnd === null) {
                // Fallback to simple delay if no session info
                $scheduledTime = $now->modify("+{$delayMinutes} minutes");
            } else {
                $scheduledTime = $presence->estimatedSessionEnd->modify("+{$postSessionBufferMinutes} minutes");
            }
        } else {
            // Fixed delay
            $scheduledTime = $now->modify("+{$delayMinutes} minutes");
        }

        // New initiation window = scheduled time + small buffer for execution tolerance
        $newInitiationWindowStart = $scheduledTime;
        $newInitiationWindowEnd = $scheduledTime->modify('+5 minutes');

        // Create new request
        $newRequest = new CallRequest(
            id: $this->generateId(),
            checkoutSessionId: $original->checkoutSessionId,
            sellerId: $original->sellerId,
            buyerId: $original->buyerId,
            buyerPhone: $original->buyerPhone,
            buyerEmail: $original->buyerEmail,
            productId: $original->productId,
            sku: $original->sku,
            pricingModel: $original->pricingModel,
            agreedPrice: $original->agreedPrice,
            currency: $original->currency,
            prepaidMinutes: $original->prepaidMinutes,
            createdAt: $now,
            initiationWindowStart: $newInitiationWindowStart,
            initiationWindowEnd: $newInitiationWindowEnd,
            callDurationMinutes: $original->callDurationMinutes,
            scheduledExecutionTime: $scheduledTime,
            status: CallRequestStatus::SCHEDULED,
            actualCallStartTime: null,
            actualCallEndTime: null,
            actualCallDurationMinutes: null,
            callCompletedSuccessfully: false,
            correlationId: $original->correlationId,
            referrerUrl: $original->referrerUrl,
            supersededByRequestId: null,
            supersededRequestId: $original->id
        );

        // Mark original as superseded
        $supersededOriginal = new CallRequest(
            id: $original->id,
            checkoutSessionId: $original->checkoutSessionId,
            sellerId: $original->sellerId,
            buyerId: $original->buyerId,
            buyerPhone: $original->buyerPhone,
            buyerEmail: $original->buyerEmail,
            productId: $original->productId,
            sku: $original->sku,
            pricingModel: $original->pricingModel,
            agreedPrice: $original->agreedPrice,
            currency: $original->currency,
            prepaidMinutes: $original->prepaidMinutes,
            createdAt: $original->createdAt,
            initiationWindowStart: $original->initiationWindowStart,
            initiationWindowEnd: $original->initiationWindowEnd,
            callDurationMinutes: $original->callDurationMinutes,
            scheduledExecutionTime: $original->scheduledExecutionTime,
            status: CallRequestStatus::SUPERSEDED,
            actualCallStartTime: $original->actualCallStartTime,
            actualCallEndTime: $original->actualCallEndTime,
            actualCallDurationMinutes: $original->actualCallDurationMinutes,
            callCompletedSuccessfully: $original->callCompletedSuccessfully,
            correlationId: $original->correlationId,
            referrerUrl: $original->referrerUrl,
            supersededByRequestId: $newRequest->id,
            supersededRequestId: $original->supersededRequestId
        );

        $this->repository->save($supersededOriginal);
        $this->repository->save($newRequest);

        $this->logger->info('dlme.call_request.rescheduled', $this->buildContext([
            'original_request_id' => $original->id,
            'new_request_id' => $newRequest->id,
            'delay_minutes' => $delayMinutes,
            'scheduled_execution_time' => $scheduledTime->format('c'),
            'seller_id' => $original->sellerId,
        ]));

        return $newRequest;
    }

    /**
     * Mark a request as expired.
     *
     * Called when initiation window passes without execution.
     *
     * @param string $requestId Request ID
     */
    public function markExpired(string $requestId): void
    {
        $request = $this->repository->findById($requestId);

        if ($request === null) {
            return;
        }

        $expired = new CallRequest(
            id: $request->id,
            checkoutSessionId: $request->checkoutSessionId,
            sellerId: $request->sellerId,
            buyerId: $request->buyerId,
            buyerPhone: $request->buyerPhone,
            buyerEmail: $request->buyerEmail,
            productId: $request->productId,
            sku: $request->sku,
            pricingModel: $request->pricingModel,
            agreedPrice: $request->agreedPrice,
            currency: $request->currency,
            prepaidMinutes: $request->prepaidMinutes,
            createdAt: $request->createdAt,
            initiationWindowStart: $request->initiationWindowStart,
            initiationWindowEnd: $request->initiationWindowEnd,
            callDurationMinutes: $request->callDurationMinutes,
            scheduledExecutionTime: $request->scheduledExecutionTime,
            status: CallRequestStatus::EXPIRED,
            actualCallStartTime: $request->actualCallStartTime,
            actualCallEndTime: $request->actualCallEndTime,
            actualCallDurationMinutes: $request->actualCallDurationMinutes,
            callCompletedSuccessfully: $request->callCompletedSuccessfully,
            correlationId: $request->correlationId,
            referrerUrl: $request->referrerUrl,
            supersededByRequestId: $request->supersededByRequestId,
            supersededRequestId: $request->supersededRequestId
        );

        $this->repository->save($expired);

        $this->logger->info('dlme.call_request.expired', $this->buildContext([
            'call_request_id' => $requestId,
            'seller_id' => $request->sellerId,
            'buyer_id' => $request->buyerId,
        ]));
    }

    /**
     * Generate unique request ID with req_ prefix.
     */
    private function generateId(): string
    {
        return 'req_' . bin2hex(random_bytes(16));
    }

    /**
     * Build log context, filtering null/empty values.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildContext(array $context): array
    {
        return array_filter($context, fn($value) => $value !== null && $value !== '');
    }
}
