<?php

use App\Enums\BookingStatus;
use App\Enums\Weekday;
use App\Jobs\SendCustomerNotification;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00', 'UTC'));

    $this->tenant = bindTenant(Tenant::factory()->create(['slug' => 'acme', 'timezone' => 'Asia/Riyadh']));
    $this->service = Service::factory()->lasting(60)->create();
    $this->sara = Staff::factory()->create();
    $this->sara->services()->attach($this->service);
    Schedule::factory()->for($this->sara)->on(Weekday::Monday, '09:00', '18:00')->create();
});

function bookingAt(string $utc, array $attributes = []): Booking
{
    return Booking::factory()
        ->for(test()->service, 'service')
        ->for(test()->sara, 'staff')
        ->startingAt(CarbonImmutable::parse($utc, 'UTC'))
        ->create(array_merge(['customer_email' => 'customer@example.com'], $attributes));
}

it('queues a reminder for a booking inside the reminder window', function () {
    Queue::fake();

    $soon = bookingAt('2026-10-02 07:00');   // 22 hours away
    $later = bookingAt('2026-10-05 07:00');  // days away

    $this->artisan('bookings:send-reminders')
        ->expectsOutputToContain('1 reminder(s) queued.')
        ->assertSuccessful();

    Queue::assertPushed(SendCustomerNotification::class, 1);
    Queue::assertPushed(fn (SendCustomerNotification $job) => in_array('notification:booking.reminder', $job->tags(), true)
        && in_array('booking:'.$soon->getKey(), $job->tags(), true));

    expect($soon->refresh()->reminder_sent_at)->not->toBeNull()
        ->and($later->refresh()->reminder_sent_at)->toBeNull();
});

it('never sends the same reminder twice, however often the scheduler runs', function () {
    Queue::fake();

    bookingAt('2026-10-02 07:00');

    $this->artisan('bookings:send-reminders')->assertSuccessful();
    $this->artisan('bookings:send-reminders')->expectsOutputToContain('0 reminder(s) queued.')->assertSuccessful();

    Queue::assertPushed(SendCustomerNotification::class, 1);
});

it('still sends a reminder that was missed while the scheduler was down', function () {
    Queue::fake();

    // Only an hour away: the 24h window was missed entirely, but it is still due.
    bookingAt('2026-10-01 10:00');

    $this->artisan('bookings:send-reminders')->expectsOutputToContain('1 reminder(s) queued.')->assertSuccessful();

    Queue::assertPushed(SendCustomerNotification::class, 1);
});

it('ignores cancelled and past bookings', function () {
    Queue::fake();

    bookingAt('2026-10-02 07:00', ['status' => BookingStatus::Cancelled, 'slot_lock' => null]);
    bookingAt('2026-09-30 07:00');

    $this->artisan('bookings:send-reminders')->expectsOutputToContain('0 reminder(s) queued.')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('reminds every tenant, each in its own context', function () {
    Queue::fake();

    $mine = bookingAt('2026-10-02 07:00');

    $other = Tenant::factory()->create(['slug' => 'other', 'timezone' => 'Asia/Riyadh']);
    $theirs = app(TenantContext::class)->runAs($other, function () {
        $service = Service::factory()->lasting(30)->create();
        $staff = Staff::factory()->create();
        $staff->services()->attach($service);

        return Booking::factory()->for($service, 'service')->for($staff, 'staff')
            ->startingAt(CarbonImmutable::parse('2026-10-02 08:00', 'UTC'))
            ->create(['customer_email' => 'other@example.com']);
    });

    $this->artisan('bookings:send-reminders')->expectsOutputToContain('2 reminder(s) queued.')->assertSuccessful();

    Queue::assertPushed(SendCustomerNotification::class, 2);
    expect($mine->refresh()->reminder_sent_at)->not->toBeNull()
        ->and(Booking::withoutTenancy()->find($theirs->getKey())->reminder_sent_at)->not->toBeNull();
});

it('respects a different reminder window', function () {
    Queue::fake();
    config(['booking.reminder_hours_before' => 48]);

    bookingAt('2026-10-03 07:00'); // 46 hours away

    $this->artisan('bookings:send-reminders')->expectsOutputToContain('1 reminder(s) queued.')->assertSuccessful();
});
