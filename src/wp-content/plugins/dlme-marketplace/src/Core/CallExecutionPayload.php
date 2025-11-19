<?php

declare(strict_types=1);

namespace DLme\Core;

use DateTimeImmutable;

/**
 * Payload sent to external call system (Asterisk/FastAGI) for call execution.
 *
 * Contains all information needed to initiate and manage the call:
 * - Participant contact info
 * - Call duration limits
 * - Pricing model
 * - Scheduling information
 * - Metadata for tracking and context
 *
 * @property-read string $callRequestId CallRequest ID being executed
 * @property-read string $consultantPhone Consultant's phone number
 * @property-read string $clientPhone Client's phone number
 * @property-read int $durationLimitMinutes Maximum call duration in minutes
 * @property-read string $pricingModel 'payg' or 'prepaid'
 * @property-read string $correlationId Correlation ID for tracking
 * @property-read DateTimeImmutable|null $scheduledExecutionTime When to execute (null for immediate)
 * @property-read array<string, mixed> $metadata Additional context metadata
 */
final readonly class CallExecutionPayload
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public string $callRequestId,
        public string $consultantPhone,
        public string $clientPhone,
        public int $durationLimitMinutes,
        public string $pricingModel,
        public string $correlationId,
        public ?DateTimeImmutable $scheduledExecutionTime = null,
        public array $metadata = [],
    ) {
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array{
     *     call_request_id: string,
     *     consultant_phone: string,
     *     client_phone: string,
     *     duration_limit_minutes: int,
     *     pricing_model: string,
     *     correlation_id: string,
     *     scheduled_execution_time: string|null,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'call_request_id' => $this->callRequestId,
            'consultant_phone' => $this->consultantPhone,
            'client_phone' => $this->clientPhone,
            'duration_limit_minutes' => $this->durationLimitMinutes,
            'pricing_model' => $this->pricingModel,
            'correlation_id' => $this->correlationId,
            'scheduled_execution_time' => $this->scheduledExecutionTime?->format('c'),
            'metadata' => $this->metadata,
        ];
    }
}
