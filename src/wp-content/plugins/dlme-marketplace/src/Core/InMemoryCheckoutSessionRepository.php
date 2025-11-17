<?php

declare(strict_types=1);

namespace DLme\Core;

use DateTimeImmutable;

/**
 * In-memory implementation of CheckoutSessionRepository for testing.
 */
final class InMemoryCheckoutSessionRepository implements CheckoutSessionRepository
{
    /**
     * @var array<string, CheckoutSession>
     */
    private array $sessionsById = [];

    /**
     * @var array<string, string>
     */
    private array $idsByIdempotencyKey = [];

    /**
     * @var array<string, string>
     */
    private array $idsByTransactionId = [];

    public function save(CheckoutSession $session): void
    {
        $this->sessionsById[$session->id] = $session;
        $this->idsByIdempotencyKey[$session->idempotencyKey] = $session->id;

        if ($session->externalTransactionId !== null) {
            $this->idsByTransactionId[$session->externalTransactionId] = $session->id;
        }
    }

    public function findById(string $id): ?CheckoutSession
    {
        return $this->sessionsById[$id] ?? null;
    }

    public function findByIdempotencyKey(string $key): ?CheckoutSession
    {
        $id = $this->idsByIdempotencyKey[$key] ?? null;
        return $id !== null ? $this->sessionsById[$id] : null;
    }

    public function findByExternalTransactionId(string $transactionId): ?CheckoutSession
    {
        $id = $this->idsByTransactionId[$transactionId] ?? null;
        return $id !== null ? $this->sessionsById[$id] : null;
    }

    public function markSucceededWithPayment(
        string $idempotencyKey,
        PaymentDetails $payment
    ): ?CheckoutSession {
        $session = $this->findByIdempotencyKey($idempotencyKey);

        if ($session === null) {
            return null;
        }

        // If already succeeded, return existing session (idempotent)
        if ($session->status === CheckoutSessionStatus::SUCCEEDED) {
            return $session;
        }

        // Only transition from PENDING to SUCCEEDED
        if ($session->status !== CheckoutSessionStatus::PENDING) {
            return $session;
        }

        // Create updated session
        $updatedSession = new CheckoutSession(
            id: $session->id,
            idempotencyKey: $session->idempotencyKey,
            sellerId: $session->sellerId,
            buyerId: $session->buyerId,
            productId: $session->productId,
            sku: $session->sku,
            quotedPrice: $session->quotedPrice,
            pageType: $session->pageType,
            ctaType: $session->ctaType,
            availabilityStatus: $session->availabilityStatus,
            referrerUrl: $session->referrerUrl,
            correlationId: $session->correlationId,
            workflowContext: $session->workflowContext,
            createdAt: $session->createdAt,
            status: CheckoutSessionStatus::SUCCEEDED,
            externalTransactionId: $payment->externalTransactionId,
            completedAt: new DateTimeImmutable(),
            failureReason: null
        );

        $this->save($updatedSession);
        return $updatedSession;
    }

    public function markFailed(string $idempotencyKey, string $reason): ?CheckoutSession
    {
        $session = $this->findByIdempotencyKey($idempotencyKey);

        if ($session === null) {
            return null;
        }

        // Don't resurrect succeeded sessions
        if ($session->status === CheckoutSessionStatus::SUCCEEDED) {
            return $session;
        }

        // If already failed, return existing session (idempotent)
        if ($session->status === CheckoutSessionStatus::FAILED) {
            return $session;
        }

        // Create updated session
        $updatedSession = new CheckoutSession(
            id: $session->id,
            idempotencyKey: $session->idempotencyKey,
            sellerId: $session->sellerId,
            buyerId: $session->buyerId,
            productId: $session->productId,
            sku: $session->sku,
            quotedPrice: $session->quotedPrice,
            pageType: $session->pageType,
            ctaType: $session->ctaType,
            availabilityStatus: $session->availabilityStatus,
            referrerUrl: $session->referrerUrl,
            correlationId: $session->correlationId,
            workflowContext: $session->workflowContext,
            createdAt: $session->createdAt,
            status: CheckoutSessionStatus::FAILED,
            externalTransactionId: $session->externalTransactionId,
            completedAt: new DateTimeImmutable(),
            failureReason: $reason
        );

        $this->save($updatedSession);
        return $updatedSession;
    }
}
