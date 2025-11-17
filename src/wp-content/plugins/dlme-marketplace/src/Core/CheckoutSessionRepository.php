<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Repository interface for checkout session persistence.
 */
interface CheckoutSessionRepository
{
    /**
     * Persists a checkout session.
     */
    public function save(CheckoutSession $session): void;

    /**
     * Finds a session by its ID.
     */
    public function findById(string $id): ?CheckoutSession;

    /**
     * Finds a session by its idempotency key.
     */
    public function findByIdempotencyKey(string $key): ?CheckoutSession;

    /**
     * Finds a session by external transaction ID.
     */
    public function findByExternalTransactionId(string $transactionId): ?CheckoutSession;

    /**
     * Marks a session as succeeded with payment details.
     *
     * Returns the updated session if it was PENDING, or the existing session if already SUCCEEDED.
     * Returns null if session not found.
     */
    public function markSucceededWithPayment(
        string $idempotencyKey,
        PaymentDetails $payment
    ): ?CheckoutSession;

    /**
     * Marks a session as failed.
     *
     * Returns the updated session if it was PENDING, or the existing session if already in a terminal state.
     * Returns null if session not found.
     */
    public function markFailed(string $idempotencyKey, string $reason): ?CheckoutSession;
}
