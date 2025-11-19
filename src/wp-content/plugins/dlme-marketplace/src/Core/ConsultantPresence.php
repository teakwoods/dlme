<?php

declare(strict_types=1);

namespace DLme\Core;

use DateTimeImmutable;

/**
 * Consultant presence snapshot from external call system webhook.
 *
 * Immutable value object representing consultant's real-time availability state.
 * Received via webhook from external system, stored for availability checks.
 *
 * @property-read string $status Status: 'in_call', 'idle', 'offline'
 * @property-read DateTimeImmutable $timestamp When this presence was recorded
 * @property-read string|null $sessionId External session ID if in_call
 * @property-read DateTimeImmutable|null $estimatedSessionEnd When current session expected to end
 * @property-read string|null $currentCallRequestId CallRequest ID being executed
 */
final readonly class ConsultantPresence
{
    public function __construct(
        public string $status,
        public DateTimeImmutable $timestamp,
        public ?string $sessionId = null,
        public ?DateTimeImmutable $estimatedSessionEnd = null,
        public ?string $currentCallRequestId = null,
    ) {
    }

    /**
     * Create from webhook payload array.
     *
     * @param array{
     *     status: string,
     *     timestamp: string,
     *     session_id?: string|null,
     *     estimated_session_end?: string|null,
     *     current_call_request_id?: string|null
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'],
            timestamp: new DateTimeImmutable($data['timestamp']),
            sessionId: $data['session_id'] ?? null,
            estimatedSessionEnd: isset($data['estimated_session_end'])
                ? new DateTimeImmutable($data['estimated_session_end'])
                : null,
            currentCallRequestId: $data['current_call_request_id'] ?? null,
        );
    }

    /**
     * Convert to array for storage.
     *
     * @return array{
     *     status: string,
     *     timestamp: string,
     *     session_id: string|null,
     *     estimated_session_end: string|null,
     *     current_call_request_id: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'timestamp' => $this->timestamp->format('c'),
            'session_id' => $this->sessionId,
            'estimated_session_end' => $this->estimatedSessionEnd?->format('c'),
            'current_call_request_id' => $this->currentCallRequestId,
        ];
    }
}
