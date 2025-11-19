<?php

declare(strict_types=1);

namespace DLme\Core;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service for preparing and validating call execution.
 *
 * Responsibilities:
 * - Build execution payloads for Asterisk/FastAGI
 * - Validate if requests can be executed
 * - Mark requests as scheduled
 */
final class CallExecutionService
{
    public function __construct(
        private readonly CallRequestRepository $repository,
        private readonly ConsultantPresenceRepository $presenceRepository,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Check if a request can be executed now.
     *
     * Returns true if:
     * - Request exists and is PENDING or SCHEDULED
     * - Within initiation window
     * - Consultant is available (if checking presence)
     */
    public function canExecuteNow(string $requestId): bool
    {
        $request = $this->repository->findById($requestId);

        if ($request === null) {
            return false;
        }

        if ($request->status !== CallRequestStatus::PENDING
            && $request->status !== CallRequestStatus::SCHEDULED) {
            return false;
        }

        $now = new \DateTimeImmutable();

        if ($now > $request->initiationWindowEnd) {
            return false;
        }

        return true;
    }

    /**
     * Build execution payload for Asterisk/FastAGI.
     */
    public function buildExecutionPayload(string $requestId): CallExecutionPayload
    {
        $request = $this->repository->findById($requestId);

        if ($request === null) {
            throw new \RuntimeException("CallRequest not found: {$requestId}");
        }

        $payload = new CallExecutionPayload(
            callRequestId: $request->id,
            consultantPhone: '+1000000000', // Will be provided by full-scope agent
            clientPhone: $request->buyerPhone,
            durationLimitMinutes: $request->callDurationMinutes,
            pricingModel: $request->pricingModel->value,
            correlationId: $request->correlationId,
            scheduledExecutionTime: $request->scheduledExecutionTime,
            metadata: [
                'checkout_session_id' => $request->checkoutSessionId,
                'seller_id' => $request->sellerId,
                'buyer_id' => $request->buyerId,
                'sku' => $request->sku,
                'agreed_price' => $request->agreedPrice,
                'currency' => $request->currency,
            ]
        );

        $this->logger->info('dlme.call_execution.payload_built', [
            'call_request_id' => $requestId,
            'pricing_model' => $request->pricingModel->value,
            'duration_limit_minutes' => $request->callDurationMinutes,
        ]);

        return $payload;
    }

    /**
     * Mark request as scheduled (sent to call system).
     */
    public function markAsScheduled(string $requestId): void
    {
        $request = $this->repository->findById($requestId);

        if ($request === null) {
            return;
        }

        $scheduled = new CallRequest(
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
            scheduledExecutionTime: $request->scheduledExecutionTime ?? new \DateTimeImmutable(),
            status: CallRequestStatus::SCHEDULED,
            actualCallStartTime: $request->actualCallStartTime,
            actualCallEndTime: $request->actualCallEndTime,
            actualCallDurationMinutes: $request->actualCallDurationMinutes,
            callCompletedSuccessfully: $request->callCompletedSuccessfully,
            correlationId: $request->correlationId,
            referrerUrl: $request->referrerUrl,
            supersededByRequestId: $request->supersededByRequestId,
            supersededRequestId: $request->supersededRequestId
        );

        $this->repository->save($scheduled);

        $this->logger->info('dlme.call_execution.scheduled', [
            'call_request_id' => $requestId,
        ]);
    }
}
