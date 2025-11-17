<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Thrown when a time range is invalid (out of bounds or start >= end).
 */
class InvalidTimeRangeException extends DomainException
{
}
