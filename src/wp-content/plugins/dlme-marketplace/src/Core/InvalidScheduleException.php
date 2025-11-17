<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Thrown when a seller schedule is invalid (overlapping ranges, invalid time ranges, etc.).
 */
class InvalidScheduleException extends DomainException
{
}
