<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Repository interface for persisting and retrieving CallRequest entities.
 *
 * Production implementation (by full-scope agent):
 * - Custom database table: wp_dlme_call_requests
 * - Indexed on: status, scheduled_execution_time, checkout_session_id
 *
 * Test implementation (SANDBOX):
 * - InMemoryCallRequestRepository with array storage
 */
interface CallRequestRepository
{
    /**
     * Save a new or updated CallRequest.
     *
     * @param CallRequest $request Request to save
     */
    public function save(CallRequest $request): void;

    /**
     * Find a CallRequest by its ID.
     *
     * @param string $id Request ID
     * @return CallRequest|null Request if found, null otherwise
     */
    public function findById(string $id): ?CallRequest;

    /**
     * Find CallRequest by checkout session ID.
     *
     * Returns the most recent (non-superseded) request for the session.
     *
     * @param string $checkoutSessionId Checkout session ID
     * @return CallRequest|null Request if found, null otherwise
     */
    public function findByCheckoutSessionId(string $checkoutSessionId): ?CallRequest;

    /**
     * Find all pending requests for a consultant.
     *
     * Returns requests with status PENDING ordered by created_at DESC.
     *
     * @param int $sellerId Consultant ID
     * @return CallRequest[] Array of pending requests
     */
    public function findPendingByConsultant(int $sellerId): array;

    /**
     * Find scheduled requests ready for execution.
     *
     * Returns requests with status SCHEDULED where scheduledExecutionTime <= now.
     *
     * @param \DateTimeInterface $asOf Time to check against
     * @return CallRequest[] Array of ready requests
     */
    public function findReadyForExecution(\DateTimeInterface $asOf): array;

    /**
     * Find requests with expired initiation windows.
     *
     * Returns requests with status PENDING or SCHEDULED where
     * initiationWindowEnd < now.
     *
     * @param \DateTimeInterface $asOf Time to check against
     * @return CallRequest[] Array of expired requests
     */
    public function findExpired(\DateTimeInterface $asOf): array;
}
