<?php

declare(strict_types=1);

namespace DLme\Core;

use DateTimeInterface;

/**
 * In-memory implementation of CallRequestRepository for testing.
 */
final class InMemoryCallRequestRepository implements CallRequestRepository
{
    /**
     * @var array<string, CallRequest>
     */
    private array $requests = [];

    public function save(CallRequest $request): void
    {
        $this->requests[$request->id] = $request;
    }

    public function findById(string $id): ?CallRequest
    {
        return $this->requests[$id] ?? null;
    }

    public function findByCheckoutSessionId(string $checkoutSessionId): ?CallRequest
    {
        $matching = array_filter(
            $this->requests,
            fn(CallRequest $r) => $r->checkoutSessionId === $checkoutSessionId
                && $r->status !== CallRequestStatus::SUPERSEDED
        );

        if (empty($matching)) {
            return null;
        }

        // Return most recent
        usort($matching, fn(CallRequest $a, CallRequest $b) => $b->createdAt <=> $a->createdAt);

        return $matching[0];
    }

    public function findPendingByConsultant(int $sellerId): array
    {
        $pending = array_filter(
            $this->requests,
            fn(CallRequest $r) => $r->sellerId === $sellerId
                && $r->status === CallRequestStatus::PENDING
        );

        usort($pending, fn(CallRequest $a, CallRequest $b) => $b->createdAt <=> $a->createdAt);

        return array_values($pending);
    }

    public function findReadyForExecution(DateTimeInterface $asOf): array
    {
        return array_values(
            array_filter(
                $this->requests,
                fn(CallRequest $r) => $r->status === CallRequestStatus::SCHEDULED
                    && $r->scheduledExecutionTime !== null
                    && $r->scheduledExecutionTime <= $asOf
            )
        );
    }

    public function findExpired(DateTimeInterface $asOf): array
    {
        return array_values(
            array_filter(
                $this->requests,
                fn(CallRequest $r) => (
                    $r->status === CallRequestStatus::PENDING
                    || $r->status === CallRequestStatus::SCHEDULED
                ) && $r->initiationWindowEnd < $asOf
            )
        );
    }

    /**
     * Clear all stored requests (for testing).
     */
    public function clear(): void
    {
        $this->requests = [];
    }
}
