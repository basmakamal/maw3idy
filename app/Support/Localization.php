<?php

namespace App\Support;

use Illuminate\Http\Request;

final class Localization
{
    /** @var list<string> */
    private const RTL_LOCALES = ['ar', 'fa', 'he', 'ur'];

    /** @var array<string, string> */
    private const NAMES = [
        'en' => 'English',
        'ar' => 'العربية',
    ];

    public const SESSION_KEY = 'locale';

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

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        /** @var list<string> $locales */
        $locales = config('tenancy.supported_locales', ['en']);

        return $locales;
    }

    public static function isSupported(?string $locale): bool
    {
        return $locale !== null && in_array($locale, self::supported(), true);
    }

    /**
     * The visitor's own choice, if they made a supported one.
     *
     * Kept in the session, which is host-only, so a choice made on one
     * tenant's subdomain never follows the visitor to another's.
     */
    public static function chosen(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $chosen = $request->session()->get(self::SESSION_KEY);

        return self::isSupported(is_string($chosen) ? $chosen : null) ? $chosen : null;
    }
}
