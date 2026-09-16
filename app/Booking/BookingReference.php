<?php

namespace App\Booking;

/**
 * Short codes customers can read out over the phone: "MW-7K3P9Q".
 * The alphabet drops 0/O and 1/I so nothing is ambiguous in print.
 */
final class BookingReference
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const LENGTH = 6;

    public const PREFIX = 'MW-';

    public static function generate(): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return self::PREFIX.$code;
    }

    public static function isValid(string $reference): bool
    {
        return preg_match('/^MW-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', $reference) === 1;
    }
}
