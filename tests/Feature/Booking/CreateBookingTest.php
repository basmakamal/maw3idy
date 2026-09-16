<?php

use App\Actions\Booking\CreateBooking;
use App\Booking\BookingReference;
use App\Data\BookingRequestData;
use App\Enums\BookingStatus;
use App\Enums\Weekday;
use App\Events\BookingCreated;
use App\Exceptions\Booking\SlotUnavailableException;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

// 2026-10-05 is a Monday; tenant in Riyadh.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00', 'UTC'));

    $this->tenant = bindTenant(Tenant::factory()->create(['timezone' => 'Asia/Riyadh']));
    $this->service = Service::factory()->lasting(60, buffer: 15)->create(['price' => '120.00']);
    $this->sara = Staff::factory()->create(['name' => 'Sara']);
    $this->sara->services()->attach($this->service);
    Schedule::factory()->for($this->sara)->on(Weekday::Monday, '09:00', '12:00')->create();

    $this->ten = CarbonImmutable::parse('2026-10-05 10:00', 'Asia/Riyadh')->utc();
    $this->action = app(CreateBooking::class);
});

function bookingRequest(int $serviceId, ?int $staffId, CarbonImmutable $start, string $name = 'Basma'): BookingRequestData
{
    return new BookingRequestData(
        serviceId: $serviceId,
        staffId: $staffId,
        start: $start,
        customerName: $name,
        customerPhone: '+966501234567',
        customerEmail: 'basma@example.com',
    );
}

it('books an available slot and snapshots the service', function () {
    Event::fake([BookingCreated::class]);

    $booking = $this->action->handle(bookingRequest($this->service->id, $this->sara->id, $this->ten));

    expect($booking)
        ->status->toBe(BookingStatus::Confirmed)
        ->slot_lock->toBeTrue()
        ->tenant_id->toBe($this->tenant->id)
        ->staff_id->toBe($this->sara->id)
        ->duration_minutes->toBe(60)
        ->buffer_after_minutes->toBe(15)
        ->price->toBe('120.00')
        ->customer_name->toBe('Basma')
        ->and($booking->starts_at->equalTo($this->ten))->toBeTrue()
        ->and($booking->ends_at->equalTo($this->ten->addHour()))->toBeTrue()
        ->and(BookingReference::isValid($booking->reference))->toBeTrue()
        ->and(strlen($booking->cancel_token))->toBe(40);

    Event::assertDispatched(BookingCreated::class, fn (BookingCreated $event) => $event->booking->is($booking));
});

it('keeps the snapshot when the service later changes', function () {
    $booking = $this->action->handle(bookingRequest($this->service->id, $this->sara->id, $this->ten));

    $this->service->update(['duration_minutes' => 30, 'price' => '999.00']);

    expect($booking->fresh())->duration_minutes->toBe(60)->price->toBe('120.00');
});

it('refuses a slot that is not offered', function (string $localTime) {
    $start = CarbonImmutable::parse("2026-10-05 $localTime", 'Asia/Riyadh')->utc();

    expect(fn () => $this->action->handle(bookingRequest($this->service->id, $this->sara->id, $start)))
        ->toThrow(SlotUnavailableException::class);

    expect(Booking::count())->toBe(0);
})->with([
    'before opening' => '08:00',
    'off the grid' => '10:30',
    'would end after closing' => '11:30',
    'on a closed day (Tuesday)' => '10:00 +1 day',
]);

it('refuses a slot in the past', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-05 10:30', 'Asia/Riyadh'));

    expect(fn () => app(CreateBooking::class)->handle(bookingRequest($this->service->id, $this->sara->id, $this->ten)))
        ->toThrow(SlotUnavailableException::class);
});

it('refuses the same slot twice and leaves exactly one booking', function () {
    $this->action->handle(bookingRequest($this->service->id, $this->sara->id, $this->ten));

    expect(fn () => $this->action->handle(bookingRequest($this->service->id, $this->sara->id, $this->ten, 'Second')))
        ->toThrow(SlotUnavailableException::class);

    expect(Booking::count())->toBe(1)
        ->and(Booking::first()->customer_name)->toBe('Basma');
});

it('refuses a slot whose buffer would collide with the next booking', function () {
    // 10:00–11:00 + 15 buffer blocks until 11:15; the 09:00 slot's own buffer (to 10:15) collides too.
    $this->action->handle(bookingRequest($this->service->id, $this->sara->id, $this->ten));

    $nine = CarbonImmutable::parse('2026-10-05 09:00', 'Asia/Riyadh')->utc();

    expect(fn () => $this->action->handle(bookingRequest($this->service->id, $this->sara->id, $nine)))
        ->toThrow(SlotUnavailableException::class);
});

it('lets a cancelled booking\'s slot be booked again', function () {
    $first = $this->action->handle(bookingRequest($this->service->id, $this->sara->id, $this->ten));
    $first->cancel('Customer called');

    $second = $this->action->handle(bookingRequest($this->service->id, $this->sara->id, $this->ten, 'Second'));

    expect($second->exists)->toBeTrue()
        ->and(Booking::confirmed()->count())->toBe(1)
        ->and(Booking::count())->toBe(2);
});

it('picks a staff member who can take the slot when the customer does not mind', function () {
    $omar = Staff::factory()->create(['name' => 'Omar']);
    $omar->services()->attach($this->service);
    Schedule::factory()->for($omar)->on(Weekday::Monday, '10:00', '13:00')->create();

    // Sara is busy at 10:00; only Omar can take it.
    Booking::factory()->for($this->service, 'service')->for($this->sara, 'staff')->startingAt($this->ten)->create();

    $booking = $this->action->handle(bookingRequest($this->service->id, null, $this->ten));

    expect($booking->staff_id)->toBe($omar->id);

    // Now nobody can.
    expect(fn () => $this->action->handle(bookingRequest($this->service->id, null, $this->ten, 'Third')))
        ->toThrow(SlotUnavailableException::class);
});

it('refuses inactive services, inactive staff and staff who do not offer the service', function () {
    $inactiveService = Service::factory()->inactive()->lasting(60)->create();
    $this->sara->services()->attach($inactiveService);

    expect(fn () => $this->action->handle(bookingRequest($inactiveService->id, $this->sara->id, $this->ten)))
        ->toThrow(ModelNotFoundException::class);

    $stranger = Staff::factory()->create();
    Schedule::factory()->for($stranger)->on(Weekday::Monday, '09:00', '12:00')->create();

    expect(fn () => $this->action->handle(bookingRequest($this->service->id, $stranger->id, $this->ten)))
        ->toThrow(SlotUnavailableException::class);

    $this->sara->update(['active' => false]);

    expect(fn () => $this->action->handle(bookingRequest($this->service->id, $this->sara->id, $this->ten)))
        ->toThrow(SlotUnavailableException::class);
});

it('never books across tenants', function () {
    $other = Tenant::factory()->create();
    $otherService = app(TenantContext::class)->runAs($other, fn () => Service::factory()->lasting(60)->create());

    expect(fn () => $this->action->handle(bookingRequest($otherService->id, $this->sara->id, $this->ten)))
        ->toThrow(ModelNotFoundException::class);
});

it('is protected by the unique index even if two requests pass the availability check together', function () {
    // Simulate the race: a competitor lands in the table after our check, right before our insert.
    Booking::creating(function () {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        DB::table('bookings')->insert([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'staff_id' => $this->sara->id,
            'reference' => 'MW-RACER1',
            'customer_name' => 'Racer',
            'customer_phone' => '+966500000000',
            'starts_at' => $this->ten->format('Y-m-d H:i:s'),
            'ends_at' => $this->ten->addHour()->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'buffer_after_minutes' => 15,
            'price' => '120.00',
            'status' => 'confirmed',
            'slot_lock' => true,
            'cancel_token' => str_repeat('r', 40),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    expect(fn () => $this->action->handle(bookingRequest($this->service->id, $this->sara->id, $this->ten)))
        ->toThrow(SlotUnavailableException::class);

    // The whole transaction rolled back: neither our booking nor the simulated competitor remain.
    expect(Booking::count())->toBe(0);
});

it('generates references that are unique per tenant', function () {
    $references = collect(range(1, 200))->map(fn () => BookingReference::generate());

    expect($references->unique())->toHaveCount(200)
        ->and($references->every(fn (string $ref) => BookingReference::isValid($ref)))->toBeTrue()
        ->and($references->first())->not->toMatch('/[01OI]/');
});
