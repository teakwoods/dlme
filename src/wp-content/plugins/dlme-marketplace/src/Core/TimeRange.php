<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Immutable value object representing a time range within a day.
 *
 * - start: int (0–23, hour of day when range begins)
 * - end: int (1–24, hour of day when range ends, exclusive)
 * - Constraint: start < end
 */
final class TimeRange
{
    private function __construct(
        private readonly int $start,
        private readonly int $end
    ) {
    }

    /**
     * Creates a new TimeRange with validation.
     *
     * @throws InvalidTimeRangeException if bounds are invalid or start >= end
     */
    public static function create(int $start, int $end): self
    {
        if ($start < 0 || $start > 23) {
            throw new InvalidTimeRangeException(
                "TimeRange start must be between 0 and 23, got {$start}"
            );
        }

        if ($end < 1 || $end > 24) {
            throw new InvalidTimeRangeException(
                "TimeRange end must be between 1 and 24, got {$end}"
            );
        }

        if ($start >= $end) {
            throw new InvalidTimeRangeException(
                "TimeRange start ({$start}) must be less than end ({$end})"
            );
        }

        return new self($start, $end);
    }

    public function start(): int
    {
        return $this->start;
    }

    public function end(): int
    {
        return $this->end;
    }

    /**
     * Checks if a given hour falls within this range.
     *
     * @param int $hour Hour (0-23)
     */
    public function contains(int $hour): bool
    {
        return $hour >= $this->start && $hour < $this->end;
    }

    /**
     * Checks if this range overlaps with another range.
     */
    public function overlaps(TimeRange $other): bool
    {
        // Ranges overlap if one starts before the other ends and vice versa
        return $this->start < $other->end && $other->start < $this->end;
    }

    /**
     * Returns array representation for serialization.
     *
     * @return array{start: int, end: int}
     */
    public function toArray(): array
    {
        return [
            'start' => $this->start,
            'end' => $this->end,
        ];
    }
}
