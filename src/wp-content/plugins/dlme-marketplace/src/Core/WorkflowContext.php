<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Context for workflow branching and custom behavior.
 *
 * Attributes are arbitrary key-value pairs that core doesn't interpret.
 */
final class WorkflowContext
{
    /**
     * @param array<string,string> $attributes
     */
    public function __construct(
        public readonly string $workflowType,
        public readonly array $attributes
    ) {
    }
}
