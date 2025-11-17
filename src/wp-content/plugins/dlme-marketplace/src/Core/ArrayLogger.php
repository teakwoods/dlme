<?php

declare(strict_types=1);

namespace DLme\Core;

use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Simple in-memory logger for testing.
 *
 * Records all log entries for assertion in tests.
 */
final class ArrayLogger extends AbstractLogger
{
    /**
     * @var array<int, array{level: string, message: string|Stringable, context: array<string, mixed>}>
     */
    public array $records = [];

    /**
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level'   => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * Clears all recorded log entries.
     */
    public function clear(): void
    {
        $this->records = [];
    }

    /**
     * Returns the number of recorded log entries.
     */
    public function count(): int
    {
        return count($this->records);
    }

    /**
     * Checks if any log entry matches the given level and message.
     */
    public function hasRecord(string $level, string $message): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && $record['message'] === $message) {
                return true;
            }
        }

        return false;
    }
}
