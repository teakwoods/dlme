<?php

declare(strict_types=1);

namespace DLme\Core;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service for handling call completion from Asterisk.
 *
 * Responsibilities:
 * - Process completion webhooks from Asterisk
 * - Update CallRequest with actual call metrics
 * - Transition to COMPLETED or FAILED status
 */
final class CallCompletionService
{
    public function __construct(
        private readonly CallRequestRepository $repository,
        private readonly LoggerInterface $logger = new NullLogger()
    ) {
    }

    /**
     * Handle call completion event from Asterisk.
     *
     * Updates request with actual call metrics and transitions to final status.
     */
    public function handleCompletion(CallCompletionEvent $event): ?CallRequest
    {
        $this->logger->info('dlme.call_completion.received', [
            'call_request_id' => $event->callRequestId,
            'status' => $event->status,
            'duration_minutes' => $event->durationMinutes,
            'consultant_answered' => $event->consultantAnswered,
            'client_answered' => $event->clientAnswered,
        ]);

        $request = $this->repository->findById($event->callRequestId);

        if ($request === null) {
            $this->logger->error('dlme.call_completion.request_not_found', [
                'call_request_id' => $event->callRequestId,
            ]);

            return null;
        }

        $finalStatus = match ($event->status) {
            'completed' => CallRequestStatus::COMPLETED,
            'failed', 'no_answer' => CallRequestStatus::FAILED,
            default => CallRequestStatus::FAILED,
        };

        $completed = new CallRequest(
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
            status: $finalStatus,
            actualCallStartTime: $event->startedAt,
            actualCallEndTime: $event->endedAt,
            actualCallDurationMinutes: $event->durationMinutes,
            callCompletedSuccessfully: $event->status === 'completed',
            correlationId: $request->correlationId,
            referrerUrl: $request->referrerUrl,
            supersededByRequestId: $request->supersededByRequestId,
            supersededRequestId: $request->supersededRequestId
        );

        $this->repository->save($completed);

        $this->logger->info('dlme.call_completion.processed', [
            'call_request_id' => $request->id,
            'final_status' => $finalStatus->value,
            'actual_duration_minutes' => $event->durationMinutes,
            'pricing_model' => $request->pricingModel->value,
        ]);

        return $completed;
    }

    /**
     * Mark request as in progress (call started).
     */
    public function markInProgress(string $requestId): void
    {
        $request = $this->repository->findById($requestId);

        if ($request === null) {
            return;
        }

        $inProgress = new CallRequest(
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
            status: CallRequestStatus::IN_PROGRESS,
            actualCallStartTime: $request->actualCallStartTime,
            actualCallEndTime: $request->actualCallEndTime,
            actualCallDurationMinutes: $request->actualCallDurationMinutes,
            callCompletedSuccessfully: $request->callCompletedSuccessfully,
            correlationId: $request->correlationId,
            referrerUrl: $request->referrerUrl,
            supersededByRequestId: $request->supersededByRequestId,
            supersededRequestId: $request->supersededRequestId
        );

        $this->repository->save($inProgress);

        $this->logger->info('dlme.call_execution.in_progress', [
            'call_request_id' => $requestId,
        ]);
    }
}
