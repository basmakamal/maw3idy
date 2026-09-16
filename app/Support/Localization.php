<?php

namespace App\Support;

final class Localization
{
    /** @var list<string> */
    private const RTL_LOCALES = ['ar', 'fa', 'he', 'ur'];

    /** @var array<string, string> */
    private const NAMES = [
        'en' => 'English',
        'ar' => 'العربية',
    ];

    /**
     * Text direction for a locale, for the <html dir> attribute.
     */
    public static function direction(string $locale): string
    {
        return in_array(substr($locale, 0, 2), self::RTL_LOCALES, true) ? 'rtl' : 'ltr';
    }

    /**
     * The locale's own name, as shown in a language switcher.
     */
    public static function name(string $locale): string
    {
        return self::NAMES[$locale] ?? $locale;
    }
}
