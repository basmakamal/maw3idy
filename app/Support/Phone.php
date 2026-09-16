<?php

namespace App\Support;

/**
 * Phone numbers as customers type them: spaces, dashes, brackets, a leading
 * "00" instead of "+", Arabic-Indic digits. Normalised to "+" plus digits, or
 * plain digits for national formats such as 05xxxxxxxx.
 */
final class Phone
{
    public const PATTERN = '/^\+?[0-9]{8,15}$/';

    private const EASTERN_DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    public static function normalize(string $raw): string
    {
        $value = trim(strtr($raw, self::EASTERN_DIGITS));
        $international = str_starts_with($value, '+');

        $digits = (string) preg_replace('/\D+/', '', $value);

        if (! $international && str_starts_with($digits, '00')) {
            $international = true;
            $digits = substr($digits, 2);
        }

        return ($international ? '+' : '').$digits;
    }

    public static function isValid(string $normalized): bool
    {
        return preg_match(self::PATTERN, $normalized) === 1;
    }
}
