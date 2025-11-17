<?php

declare(strict_types=1);

namespace DLme\Core;

/**
 * Enum representing days of the week.
 *
 * Valid values: mon, tue, wed, thu, fri, sat, sun
 */
enum DayOfWeek: string
{
    case MONDAY    = 'mon';
    case TUESDAY   = 'tue';
    case WEDNESDAY = 'wed';
    case THURSDAY  = 'thu';
    case FRIDAY    = 'fri';
    case SATURDAY  = 'sat';
    case SUNDAY    = 'sun';

    /**
     * Returns all days in order (Monday through Sunday).
     *
     * @return array<DayOfWeek>
     */
    public static function all(): array
    {
        return [
            self::MONDAY,
            self::TUESDAY,
            self::WEDNESDAY,
            self::THURSDAY,
            self::FRIDAY,
            self::SATURDAY,
            self::SUNDAY,
        ];
    }

}
