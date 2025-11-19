<?php

declare(strict_types=1);

namespace DLme\Core;

use DateTimeImmutable;

/**
 * Event received from external call system (Asterisk) when call completes.
 *
 * Contains outcome information:
 * - Final status (completed, failed, no_answer)
 * - Actual call timing (start, end, duration)
 * - Participant answer status
 * - Disconnect reason
 *
 * Used to update CallRequest with actual execution metrics,
 * especially important for PAYG billing based on actual duration.
 *
 * @property-read string $callRequestId CallRequest ID that was executed
 * @property-read string $status 'completed', 'failed', or 'no_answer'
 * @property-read DateTimeImmutable $startedAt When call started
 * @property-read DateTimeImmutable $endedAt When call ended
 * @property-read int $durationMinutes Actual call duration in minutes
 * @property-read bool $consultantAnswered Whether consultant answered
 * @property-read bool $clientAnswered Whether client answered
 * @property-read string $disconnectReason Reason: 'normal', 'consultant_hangup', 'client_hangup', 'timeout', 'error'
 */
final readonly class CallCompletionEvent
{
    public function __construct(
        public string $callRequestId,
        public string $status,
        public DateTimeImmutable $startedAt,
        public DateTimeImmutable $endedAt,
        public int $durationMinutes,
        public bool $consultantAnswered,
        public bool $clientAnswered,
        public string $disconnectReason,
    ) {
    }

    /**
     * Create from webhook payload array.
     *
     * @param array{
     *     call_request_id: string,
     *     status: string,
     *     started_at: string,
     *     ended_at: string,
     *     duration_minutes: int,
     *     consultant_answered: bool,
     *     client_answered: bool,
     *     disconnect_reason: string
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            callRequestId: $data['call_request_id'],
            status: $data['status'],
            startedAt: new DateTimeImmutable($data['started_at']),
            endedAt: new DateTimeImmutable($data['ended_at']),
            durationMinutes: $data['duration_minutes'],
            consultantAnswered: $data['consultant_answered'],
            clientAnswered: $data['client_answered'],
            disconnectReason: $data['disconnect_reason'],
        );
    }

    /**
     * Convert to array for storage/logging.
     *
     * @return array{
     *     call_request_id: string,
     *     status: string,
     *     started_at: string,
     *     ended_at: string,
     *     duration_minutes: int,
     *     consultant_answered: bool,
     *     client_answered: bool,
     *     disconnect_reason: string
     * }
     */
    public function toArray(): array
    {
        return [
            'call_request_id' => $this->callRequestId,
            'status' => $this->status,
            'started_at' => $this->startedAt->format('c'),
            'ended_at' => $this->endedAt->format('c'),
            'duration_minutes' => $this->durationMinutes,
            'consultant_answered' => $this->consultantAnswered,
            'client_answered' => $this->clientAnswered,
            'disconnect_reason' => $this->disconnectReason,
        ];
    }
}
