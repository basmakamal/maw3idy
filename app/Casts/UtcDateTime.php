<?php

namespace App\Casts;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Store every instant as UTC, whatever timezone the caller's Carbon carried.
 *
 * Eloquent's built-in datetime cast formats a Carbon instance in the instance's
 * own timezone; hand it "10:00 Asia/Riyadh" and the column reads 10:00, which
 * is then reloaded as 10:00 UTC, three hours late. Availability maths cannot
 * survive that, so the conversion is made impossible to forget.
 *
 * @implements CastsAttributes<CarbonImmutable, DateTimeInterface|string>
 */
final class UtcDateTime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value, 'UTC');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $instant = $value instanceof DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse((string) $value, 'UTC');

        return $instant->utc()->format('Y-m-d H:i:s');
    }
}
