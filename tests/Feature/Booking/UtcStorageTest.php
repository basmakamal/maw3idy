<?php

use App\Models\Booking;
use App\Models\Tenant;
use App\Models\TimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    bindTenant(Tenant::factory()->create(['timezone' => 'Asia/Riyadh']));
});

it('stores booking instants as UTC regardless of the timezone they were given in', function () {
    $booking = Booking::factory()
        ->startingAt(CarbonImmutable::parse('2026-10-05 10:00', 'Asia/Riyadh'))
        ->create();

    $raw = DB::table('bookings')->where('id', $booking->id)->value('starts_at');

    expect($raw)->toBe('2026-10-05 07:00:00')
        ->and($booking->fresh()->starts_at->format('Y-m-d H:i e'))->toBe('2026-10-05 07:00 UTC')
        ->and($booking->fresh()->starts_at->setTimezone('Asia/Riyadh')->format('H:i'))->toBe('10:00');
});

it('stores time off as UTC and accepts plain strings as UTC', function () {
    $absence = TimeOff::factory()->between(
        CarbonImmutable::parse('2026-10-05 09:00', 'Asia/Riyadh'),
        CarbonImmutable::parse('2026-10-05 10:30', 'Asia/Riyadh'),
    )->create();

    expect(DB::table('time_off')->where('id', $absence->id)->value('starts_at'))->toBe('2026-10-05 06:00:00')
        ->and(DB::table('time_off')->where('id', $absence->id)->value('ends_at'))->toBe('2026-10-05 07:30:00');

    $absence->update(['starts_at' => '2026-10-06 08:00:00']);

    expect($absence->fresh()->starts_at->format('Y-m-d H:i e'))->toBe('2026-10-06 08:00 UTC');
});
