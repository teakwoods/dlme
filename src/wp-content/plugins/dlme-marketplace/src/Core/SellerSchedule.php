<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Immutable value object representing a seller's weekly schedule.
 *
 * - Contains a map of DayOfWeek → TimeRange[]
 * - All 7 days must be present
 * - Time ranges within each day must be non-overlapping and sorted by start time
 * - Empty arrays are allowed (seller has no availability that day)
 */
final class SellerSchedule
{
    /**
     * @var array<string, array<TimeRange>>
     */
    private readonly array $schedule;

    /**
     * @param array<string, array<TimeRange>> $schedule
     */
    private function __construct(array $schedule)
    {
        $this->schedule = $schedule;
    }

    /**
     * Creates a normalized SellerSchedule from raw input.
     *
     * Normalization includes:
     * - Ensuring all 7 days are present
     * - Sorting ranges by start time
     * - Validating no overlaps within each day
     *
     * @param array<string, array<mixed>> $rawSchedule
     * @throws InvalidScheduleException if validation fails
     */
    public static function create(array $rawSchedule): self
    {
        $normalized = [];

        // Ensure all days are present
        foreach (DayOfWeek::all() as $day) {
            $dayValue = $day->value;
            $rawRanges = $rawSchedule[$dayValue] ?? [];

            // Convert to TimeRange objects if needed
            $ranges = [];
            foreach ($rawRanges as $range) {
                if ($range instanceof TimeRange) {
                    $ranges[] = $range;
                } elseif (is_array($range) && array_key_exists('start', $range) && array_key_exists('end', $range)) {
                    try {
                        $ranges[] = TimeRange::create((int)$range['start'], (int)$range['end']);
                    } catch (InvalidTimeRangeException $e) {
                        throw new InvalidScheduleException(
                            "Invalid time range for {$dayValue}: {$e->getMessage()}",
                            0,
                            $e
                        );
                    }
                } else {
                    throw new InvalidScheduleException(
                        "Invalid time range format for {$dayValue}"
                    );
                }
            }

            // Sort by start time
            usort($ranges, fn(TimeRange $a, TimeRange $b) => $a->start() <=> $b->start());

            // Check for overlaps
            for ($i = 0; $i < count($ranges) - 1; $i++) {
                if ($ranges[$i]->overlaps($ranges[$i + 1])) {
                    throw new InvalidScheduleException(
                        "Overlapping time ranges detected for {$dayValue}"
                    );
                }
            }

            $normalized[$dayValue] = $ranges;
        }

        return new self($normalized);
    }

    /**
     * Returns time ranges for a specific day.
     *
     * @return array<TimeRange>
     */
    public function getRangesForDay(DayOfWeek $day): array
    {
        return $this->schedule[$day->value];
    }

    /**
     * Checks if a given hour is within scheduled hours for a specific day.
     */
    public function isWithinSchedule(DayOfWeek $day, int $hour): bool
    {
        $ranges = $this->getRangesForDay($day);

        foreach ($ranges as $range) {
            if ($range->contains($hour)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns array representation for serialization.
     *
     * @return array<string, array<array{start: int, end: int}>>
     */
    public function toArray(): array
    {
        $result = [];

        foreach (DayOfWeek::all() as $day) {
            $dayValue = $day->value;
            $result[$dayValue] = array_map(
                fn(TimeRange $range) => $range->toArray(),
                $this->schedule[$dayValue]
            );
        }

        return $result;
    }
}
