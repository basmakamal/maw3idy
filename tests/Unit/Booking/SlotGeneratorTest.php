<?php

use App\Booking\Availability\Period;
use App\Booking\Availability\SlotGenerator;
use App\Booking\Availability\SlotRequest;
use App\Booking\Availability\StaffCalendar;
use App\Booking\Availability\WorkingHours;
use App\Enums\Weekday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/*
 * Fixture vocabulary. 2026-10-05 is a Monday. Riyadh is UTC+3 with no DST.
 */

const RIYADH = 'Asia/Riyadh';

function generator(string $now = '2026-10-01 12:00'): SlotGenerator
{
    return new SlotGenerator(CarbonImmutable::parse($now, 'UTC'));
}

function slotRequest(string $day = '2026-10-05', int $duration = 60, int $buffer = 0, ?int $interval = null, string $tz = RIYADH): SlotRequest
{
    return new SlotRequest($day, $tz, $duration, $buffer, $interval);
}

function hours(Weekday $weekday, string $start, string $end): WorkingHours
{
    return new WorkingHours($weekday, $start, $end);
}

/** A local (tenant-time) interval on the fixture day. */
function local(string $start, string $end, string $day = '2026-10-05', string $tz = RIYADH): Period
{
    return new Period(CarbonImmutable::parse("$day $start", $tz), CarbonImmutable::parse("$day $end", $tz));
}

/** An existing booking as the engine expects it: its own buffer already appended. */
function booked(string $start, int $minutes, int $buffer = 0, string $day = '2026-10-05'): Period
{
    return Period::fromMinutes(CarbonImmutable::parse("$day $start", RIYADH), $minutes)->extendedBy($buffer);
}

/** @return list<string> local start times, e.g. ['09:00', '10:00'] */
function localStarts(Collection $slots, string $tz = RIYADH): array
{
    return $slots->map(fn (Period $slot) => $slot->start->setTimezone($tz)->format('H:i'))->all();
}

$monday = fn (string $start = '09:00', string $end = '12:00') => new StaffCalendar(hours: [hours(Weekday::Monday, $start, $end)]);

/*
 * Working hours
 */

it('offers back-to-back slots across a working block', function () use ($monday) {
    $slots = generator()->generate(slotRequest(duration: 60), $monday());

    expect(localStarts($slots))->toBe(['09:00', '10:00', '11:00']);
});

it('requires the service itself to end by closing time', function () use ($monday) {
    expect(localStarts(generator()->generate(slotRequest(duration: 45), $monday())))->toBe(['09:00', '09:45', '10:30', '11:15'])
        ->and(localStarts(generator()->generate(slotRequest(duration: 50), $monday())))->toBe(['09:00', '09:50', '10:40']);
});

it('lets the buffer spill past closing time', function () use ($monday) {
    // 11:30–12:00 fits; its 15-minute buffer running to 12:15 is the staff member's problem, not the customer's.
    $slots = generator()->generate(slotRequest(duration: 30, buffer: 15), $monday());

    expect(localStarts($slots))->toContain('11:30');
});

it('offers nothing on a day without working hours', function () use ($monday) {
    $tuesday = slotRequest(day: '2026-10-06');

    expect(generator()->generate($tuesday, $monday()))->toBeEmpty();
});

it('offers nothing when the working block is shorter than the service', function () {
    $calendar = new StaffCalendar(hours: [hours(Weekday::Monday, '09:00', '09:45')]);

    expect(generator()->generate(slotRequest(duration: 60), $calendar))->toBeEmpty();
});

it('supports split shifts', function () {
    $calendar = new StaffCalendar(hours: [
        hours(Weekday::Monday, '09:00', '11:00'),
        hours(Weekday::Monday, '14:00', '16:00'),
    ]);

    expect(localStarts(generator()->generate(slotRequest(duration: 60), $calendar)))
        ->toBe(['09:00', '10:00', '14:00', '15:00']);
});

it('does not duplicate slots when working blocks overlap', function () {
    $calendar = new StaffCalendar(hours: [
        hours(Weekday::Monday, '09:00', '11:00'),
        hours(Weekday::Monday, '10:00', '12:00'),
    ]);

    expect(localStarts(generator()->generate(slotRequest(duration: 60), $calendar)))
        ->toBe(['09:00', '10:00', '11:00']);
});

it('ignores hours on other weekdays', function () {
    $calendar = new StaffCalendar(hours: [
        hours(Weekday::Tuesday, '09:00', '17:00'),
        hours(Weekday::Monday, '13:00', '14:00'),
    ]);

    expect(localStarts(generator()->generate(slotRequest(duration: 60), $calendar)))->toBe(['13:00']);
});

/*
 * Existing bookings and buffers
 */

it('removes slots that overlap an existing booking', function () use ($monday) {
    $calendar = new StaffCalendar(hours: $monday()->hours, bookings: [booked('10:00', 60)]);

    expect(localStarts(generator()->generate(slotRequest(duration: 60), $calendar)))->toBe(['09:00', '11:00']);
});

it('allows true back-to-back bookings when there is no buffer', function () use ($monday) {
    $calendar = new StaffCalendar(hours: $monday()->hours, bookings: [booked('10:00', 30)]);

    expect(localStarts(generator()->generate(slotRequest(duration: 30), $calendar)))
        ->toBe(['09:00', '09:30', '10:30', '11:00', '11:30']);
});

it('keeps the existing booking\'s buffer free', function () use ($monday) {
    // 10:00–10:30 with a 15-minute clean-up blocks until 10:45, so 10:30 is gone too.
    $calendar = new StaffCalendar(hours: $monday()->hours, bookings: [booked('10:00', 30, buffer: 15)]);

    expect(localStarts(generator()->generate(slotRequest(duration: 30), $calendar)))
        ->toBe(['09:00', '09:30', '11:00', '11:30']);
});

it('keeps the new booking\'s own buffer clear of the next booking', function () use ($monday) {
    // A 30-minute service with a 15-minute buffer at 09:30 would still be cleaning up at 10:00.
    $calendar = new StaffCalendar(hours: $monday()->hours, bookings: [booked('10:00', 30)]);

    expect(localStarts(generator()->generate(slotRequest(duration: 30, buffer: 15), $calendar)))
        ->toBe(['09:00', '10:30', '11:00', '11:30']);
});

it('respects a booking from the previous day that spills into this one', function () {
    // Night shift: 23:30 Sunday to 00:30 Monday; Monday's first block starts at midnight.
    $calendar = new StaffCalendar(
        hours: [hours(Weekday::Monday, '00:00', '02:00')],
        bookings: [booked('23:30', 60, day: '2026-10-04')],
    );

    expect(localStarts(generator()->generate(slotRequest(duration: 30), $calendar)))
        ->toBe(['00:30', '01:00', '01:30']);
});

/*
 * Time off
 */

it('removes slots that overlap time off', function () use ($monday) {
    $calendar = new StaffCalendar(hours: $monday()->hours, timeOff: [local('10:15', '10:45')]);

    expect(localStarts(generator()->generate(slotRequest(duration: 30), $calendar)))
        ->toBe(['09:00', '09:30', '11:00', '11:30']);
});

it('offers nothing during a whole-day absence', function () use ($monday) {
    $calendar = new StaffCalendar(hours: $monday()->hours, timeOff: [local('00:00', '23:59')]);

    expect(generator()->generate(slotRequest(), $calendar))->toBeEmpty();
});

it('lets the buffer overlap time off, unlike the service itself', function () use ($monday) {
    // Service 09:30–10:00 ends exactly when the break starts; its buffer running into the break is fine.
    $calendar = new StaffCalendar(hours: $monday()->hours, timeOff: [local('10:00', '10:30')]);

    expect(localStarts(generator()->generate(slotRequest(duration: 30, buffer: 15), $calendar)))
        ->toContain('09:30')
        ->not->toContain('10:00');
});

/*
 * Time
 */

it('never offers a start in the past', function () use ($monday) {
    // "Now" is 10:20 Riyadh time on the fixture day.
    $slots = generator(now: '2026-10-05 07:20')->generate(slotRequest(duration: 30), $monday());

    expect(localStarts($slots))->toBe(['10:30', '11:00', '11:30']);
});

it('treats a start exactly at "now" as past', function () use ($monday) {
    $slots = generator(now: '2026-10-05 07:00')->generate(slotRequest(duration: 30), $monday());

    expect(localStarts($slots))->not->toContain('10:00')->toContain('10:30');
});

it('returns UTC instants that correspond to the tenant\'s local hours', function () use ($monday) {
    $slots = generator()->generate(slotRequest(duration: 60), $monday());

    expect($slots->first()->start->format('Y-m-d H:i e'))->toBe('2026-10-05 06:00 UTC')
        ->and($slots->first()->start->setTimezone(RIYADH)->format('H:i'))->toBe('09:00');
});

it('keeps local opening hours across a DST transition', function (string $day, string $expectedFirstUtc) {
    // Europe/London: 2026-03-29 springs forward (23-hour day), 2026-10-25 falls back (25-hour day).
    $calendar = new StaffCalendar(hours: [
        hours(Weekday::Sunday, '09:00', '17:00'),
    ]);

    $slots = generator(now: '2026-01-01 00:00')->generate(slotRequest(day: $day, duration: 60, tz: 'Europe/London'), $calendar);

    expect(localStarts($slots, 'Europe/London'))->toBe(['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00'])
        ->and($slots->first()->start->format('Y-m-d H:i'))->toBe($expectedFirstUtc);
})->with([
    'spring forward' => ['2026-03-29', '2026-03-29 08:00'],
    'fall back' => ['2026-10-25', '2026-10-25 09:00'],
    'ordinary summer day' => ['2026-07-05', '2026-07-05 08:00'],
]);

it('skips a working block that a DST jump swallows entirely', function () {
    // 01:00–02:00 does not exist in London on 2026-03-29.
    $calendar = new StaffCalendar(hours: [hours(Weekday::Sunday, '01:00', '02:00')]);

    expect(generator(now: '2026-01-01 00:00')->generate(slotRequest(day: '2026-03-29', duration: 30, tz: 'Europe/London'), $calendar))->toBeEmpty();
});

/*
 * Grid
 */

it('offers a finer grid when an interval is given', function () use ($monday) {
    $slots = generator()->generate(slotRequest(duration: 30, interval: 15), $monday('09:00', '10:00'));

    expect(localStarts($slots))->toBe(['09:00', '09:15', '09:30']);
});

it('rejects impossible requests early', function () {
    expect(fn () => slotRequest(duration: 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => slotRequest(buffer: -1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => slotRequest(interval: 0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => slotRequest(day: '05/10/2026'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => hours(Weekday::Monday, '17:00', '09:00'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => hours(Weekday::Monday, '9am', '17:00'))->toThrow(InvalidArgumentException::class);
});

it('accepts a Carbon date as the day', function () use ($monday) {
    $request = new SlotRequest(CarbonImmutable::parse('2026-10-05', RIYADH), RIYADH, 60);

    expect(localStarts(generator()->generate($request, $monday())))->toBe(['09:00', '10:00', '11:00']);
});
