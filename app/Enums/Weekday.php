<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * ISO-8601 weekday numbers, the same ones Carbon's dayOfWeekIso returns.
 */
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public static function of(CarbonInterface $date): self
    {
        return self::from($date->dayOfWeekIso);
    }

    public function label(): string
    {
        return __($this->name);
    }

    /**
     * The week in display order for the region: Saudi weeks start on Sunday.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Sunday, self::Monday, self::Tuesday, self::Wednesday, self::Thursday, self::Friday, self::Saturday];
    }
}
