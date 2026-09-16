<?php

use App\Booking\Availability\Period;
use Carbon\CarbonImmutable;

function utcPeriod(string $start, string $end): Period
{
    return new Period(CarbonImmutable::parse($start, 'UTC'), CarbonImmutable::parse($end, 'UTC'));
}

it('refuses an empty or inverted period', function () {
    expect(fn () => utcPeriod('2026-10-05 10:00', '2026-10-05 10:00'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => utcPeriod('2026-10-05 10:00', '2026-10-05 09:00'))->toThrow(InvalidArgumentException::class);
});

it('normalises both ends to UTC', function () {
    $riyadh = new Period(
        CarbonImmutable::parse('2026-10-05 09:00', 'Asia/Riyadh'),
        CarbonImmutable::parse('2026-10-05 10:00', 'Asia/Riyadh'),
    );

    expect($riyadh->start->format('Y-m-d H:i e'))->toBe('2026-10-05 06:00 UTC')
        ->and($riyadh->minutes())->toBe(60);
});

it('treats periods that merely touch as not overlapping', function () {
    $morning = utcPeriod('2026-10-05 09:00', '2026-10-05 10:00');
    $next = utcPeriod('2026-10-05 10:00', '2026-10-05 11:00');

    expect($morning->overlaps($next))->toBeFalse()
        ->and($next->overlaps($morning))->toBeFalse();
});

it('detects partial, nested and identical overlaps', function (string $aStart, string $aEnd, string $bStart, string $bEnd) {
    $a = utcPeriod("2026-10-05 $aStart", "2026-10-05 $aEnd");
    $b = utcPeriod("2026-10-05 $bStart", "2026-10-05 $bEnd");

    expect($a->overlaps($b))->toBeTrue()->and($b->overlaps($a))->toBeTrue();
})->with([
    'partial' => ['09:00', '10:00', '09:30', '10:30'],
    'nested' => ['09:00', '12:00', '10:00', '11:00'],
    'identical' => ['09:00', '10:00', '09:00', '10:00'],
    'one minute' => ['09:00', '10:00', '09:59', '11:00'],
]);

it('knows when it encloses another period', function () {
    $day = utcPeriod('2026-10-05 09:00', '2026-10-05 17:00');

    expect($day->encloses(utcPeriod('2026-10-05 09:00', '2026-10-05 17:00')))->toBeTrue()
        ->and($day->encloses(utcPeriod('2026-10-05 10:00', '2026-10-05 11:00')))->toBeTrue()
        ->and($day->encloses(utcPeriod('2026-10-05 16:30', '2026-10-05 17:01')))->toBeFalse()
        ->and($day->encloses(utcPeriod('2026-10-05 08:59', '2026-10-05 09:30')))->toBeFalse();
});

it('extends by a buffer without moving the start', function () {
    $slot = utcPeriod('2026-10-05 09:00', '2026-10-05 09:30');

    expect($slot->extendedBy(10)->end->format('H:i'))->toBe('09:40')
        ->and($slot->extendedBy(10)->start->format('H:i'))->toBe('09:00')
        ->and($slot->extendedBy(0))->toBe($slot);
});

it('builds from a start and a length', function () {
    $slot = Period::fromMinutes(CarbonImmutable::parse('2026-10-05 09:00', 'UTC'), 45);

    expect($slot->end->format('H:i'))->toBe('09:45')->and($slot->minutes())->toBe(45);
});
