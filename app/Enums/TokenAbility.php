<?php

namespace App\Enums;

/**
 * What an API token is allowed to do. A token carries an explicit list, so an
 * integration that only needs to read the diary cannot create bookings.
 */
enum TokenAbility: string
{
    case ReadServices = 'services:read';
    case ReadBookings = 'bookings:read';
    case WriteBookings = 'bookings:write';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $ability) => $ability->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::ReadServices => __('Read services'),
            self::ReadBookings => __('Read bookings'),
            self::WriteBookings => __('Create and cancel bookings'),
        };
    }
}
