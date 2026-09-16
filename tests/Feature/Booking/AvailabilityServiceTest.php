<?php

use App\Booking\Availability\AvailabilityService;
use App\Booking\Availability\AvailableSlot;
use App\Enums\Weekday;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

// 2026-10-05 is a Monday; the tenant is in Riyadh (UTC+3).
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00', 'UTC'));

    $this->tenant = bindTenant(Tenant::factory()->create(['timezone' => 'Asia/Riyadh']));
    $this->service = Service::factory()->lasting(60)->create();
    $this->staff = Staff::factory()->create(['name' => 'Sara']);
    $this->staff->services()->attach($this->service);
    Schedule::factory()->for($this->staff)->on(Weekday::Monday, '09:00', '12:00')->create();

    $this->availability = app(AvailabilityService::class);
});

/** @return list<string> */
function riyadhStarts(Collection $slots): array
{
    return $slots->map(fn (AvailableSlot $slot) => $slot->start()->setTimezone('Asia/Riyadh')->format('H:i'))->all();
}

it('derives a staff member\'s slots from their schedule in the tenant timezone', function () {
    $slots = $this->availability->slotsFor($this->service, '2026-10-05', $this->staff);

    expect(riyadhStarts($slots))->toBe(['09:00', '10:00', '11:00'])
        ->and($slots->first()->start()->format('H:i e'))->toBe('06:00 UTC')
        ->and($slots->first()->staffIds)->toBe([$this->staff->id]);
});

it('blocks confirmed bookings including their buffer, but not cancelled ones', function () {
    $bufferService = Service::factory()->lasting(30, buffer: 15)->create();

    Booking::factory()->for($bufferService, 'service')->for($this->staff, 'staff')
        ->startingAt(CarbonImmutable::parse('2026-10-05 10:00', 'Asia/Riyadh'))
        ->create();

    Booking::factory()->for($this->service, 'service')->for($this->staff, 'staff')
        ->startingAt(CarbonImmutable::parse('2026-10-05 09:00', 'Asia/Riyadh'))
        ->cancelled()
        ->create();

    // 60-minute service: 09:00 is free (the booking there is cancelled); 10:00 collides with 10:00–10:45.
    expect(riyadhStarts($this->availability->slotsFor($this->service, '2026-10-05', $this->staff)))
        ->toBe(['09:00', '11:00']);
});

it('blocks time off', function () {
    TimeOff::factory()->for($this->staff)->between(
        CarbonImmutable::parse('2026-10-05 09:00', 'Asia/Riyadh'),
        CarbonImmutable::parse('2026-10-05 10:30', 'Asia/Riyadh'),
    )->create();

    expect(riyadhStarts($this->availability->slotsFor($this->service, '2026-10-05', $this->staff)))->toBe(['11:00']);
});

it('merges slots across every active staff member who offers the service', function () {
    $omar = Staff::factory()->create(['name' => 'Omar']);
    $omar->services()->attach($this->service);
    Schedule::factory()->for($omar)->on(Weekday::Monday, '10:00', '13:00')->create();

    $inactive = Staff::factory()->inactive()->create();
    $inactive->services()->attach($this->service);
    Schedule::factory()->for($inactive)->on(Weekday::Monday, '08:00', '18:00')->create();

    $otherSkill = Staff::factory()->create(); // offers nothing
    Schedule::factory()->for($otherSkill)->on(Weekday::Monday, '08:00', '18:00')->create();

    $slots = $this->availability->slotsFor($this->service, '2026-10-05');

    expect(riyadhStarts($slots))->toBe(['09:00', '10:00', '11:00', '12:00']);

    $at = fn (string $time) => $slots->first(fn (AvailableSlot $s) => $s->start()->setTimezone('Asia/Riyadh')->format('H:i') === $time);

    expect($at('09:00')->staffIds)->toBe([$this->staff->id])
        ->and($at('10:00')->staffIds)->toEqualCanonicalizing([$this->staff->id, $omar->id])
        ->and($at('12:00')->staffIds)->toBe([$omar->id]);
});

it('never sees another tenant\'s bookings or staff', function () {
    $other = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    app(TenantContext::class)->runAs($other, function () {
        $service = Service::factory()->lasting(60)->create();
        $staff = Staff::factory()->create();
        $staff->services()->attach($service);
        Schedule::factory()->for($staff)->on(Weekday::Monday, '09:00', '12:00')->create();
        Booking::factory()->for($service, 'service')->for($staff, 'staff')
            ->startingAt(CarbonImmutable::parse('2026-10-05 09:00', 'Asia/Riyadh'))->create();
    });

    expect(riyadhStarts($this->availability->slotsFor($this->service, '2026-10-05')))->toBe(['09:00', '10:00', '11:00']);
});

it('answers whether an exact start is still available', function () {
    $ten = CarbonImmutable::parse('2026-10-05 10:00', 'Asia/Riyadh');

    expect($this->availability->isAvailable($this->service, $this->staff, $ten))->toBeTrue();

    Booking::factory()->for($this->service, 'service')->for($this->staff, 'staff')->startingAt($ten)->create();

    expect($this->availability->isAvailable($this->service, $this->staff, $ten))->toBeFalse()
        ->and($this->availability->isAvailable($this->service, $this->staff, $ten->addMinutes(30)))->toBeFalse() // off-grid
        ->and($this->availability->isAvailable($this->service, $this->staff, $ten->addHour()))->toBeTrue();
});

it('hides slots that are already in the past', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 10:30', 'Asia/Riyadh'));

    // The engine is handed "now" when resolved, so resolve it after travelling.
    expect(riyadhStarts(app(AvailabilityService::class)->slotsFor($this->service, '2026-10-05', $this->staff)))->toBe(['11:00']);
});

it('honours a configured slot interval', function () {
    config(['booking.slot_interval_minutes' => 30]);

    expect(riyadhStarts(app(AvailabilityService::class)->slotsFor($this->service, '2026-10-05', $this->staff)))
        ->toBe(['09:00', '09:30', '10:00', '10:30', '11:00']);
});
