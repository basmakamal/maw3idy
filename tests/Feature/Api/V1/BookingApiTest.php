<?php

use App\Enums\BookingStatus;
use App\Enums\TokenAbility;
use App\Enums\Weekday;
use App\Mail\CustomerNotificationMail;
use App\Messaging\BookingCancelledNotification;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;

// 2026-10-05 is a Monday; tenant in Riyadh (UTC+3).
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00', 'UTC'));

    $this->tenant = bindTenant(Tenant::factory()->create(['slug' => 'acme', 'timezone' => 'Asia/Riyadh']));
    $this->owner = User::factory()->owner()->create();

    $this->haircut = Service::factory()->lasting(60, buffer: 15)->create(['name' => 'Haircut', 'price' => '80.00']);
    $this->sara = Staff::factory()->create(['name' => 'Sara']);
    $this->sara->services()->attach($this->haircut);
    Schedule::factory()->for($this->sara)->on(Weekday::Monday, '09:00', '13:00')->create();

    $this->ten = CarbonImmutable::parse('2026-10-05 10:00', 'Asia/Riyadh')->utc();

    $this->readToken = $this->owner->createToken('Reader', [TokenAbility::ReadBookings->value])->plainTextToken;
    $this->writeToken = $this->owner->createToken('Writer', [TokenAbility::ReadBookings->value, TokenAbility::WriteBookings->value])->plainTextToken;
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function bookingPayload(array $overrides = []): array
{
    return array_merge([
        'service_id' => test()->haircut->id,
        'staff_id' => test()->sara->id,
        'starts_at' => test()->ten->toIso8601String(),
        'customer_name' => 'Basma',
        'customer_phone' => '050 123 4567',
        'customer_email' => 'basma@example.com',
    ], $overrides);
}

it('creates a booking and returns it', function () {
    $response = $this->withToken($this->writeToken)
        ->postJson(apiUrl($this->tenant, '/bookings'), bookingPayload())
        ->assertCreated()
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.starts_at', '2026-10-05T07:00:00+00:00')
        ->assertJsonPath('data.ends_at', '2026-10-05T08:00:00+00:00')
        ->assertJsonPath('data.duration_minutes', 60)
        ->assertJsonPath('data.buffer_after_minutes', 15)
        ->assertJsonPath('data.price', '80.00')
        ->assertJsonPath('data.customer.name', 'Basma')
        ->assertJsonPath('data.customer.phone', '0501234567')
        ->assertJsonPath('data.service.name', 'Haircut')
        ->assertJsonPath('data.staff.name', 'Sara')
        ->assertJsonPath('meta.timezone', 'Asia/Riyadh');

    betweenRequests($this->tenant);

    $booking = Booking::sole();

    expect($response->json('data.reference'))->toBe($booking->reference)
        ->and($response->json('data'))->not->toHaveKey('cancel_token');
});

it('picks a staff member when the caller does not name one', function () {
    $this->withToken($this->writeToken)
        ->postJson(apiUrl($this->tenant, '/bookings'), bookingPayload(['staff_id' => null]))
        ->assertCreated()
        ->assertJsonPath('data.staff.id', $this->sara->id);
});

it('answers 409 when the slot has gone', function () {
    Booking::factory()->for($this->haircut, 'service')->for($this->sara, 'staff')->startingAt($this->ten)->create();

    $this->withToken($this->writeToken)
        ->postJson(apiUrl($this->tenant, '/bookings'), bookingPayload())
        ->assertStatus(409)
        ->assertJsonPath('message', 'The slot starting at 2026-10-05T07:00:00+00:00 is no longer available.');

    betweenRequests($this->tenant);

    expect(Booking::count())->toBe(1);
});

it('validates the payload', function () {
    $this->withToken($this->writeToken)
        ->postJson(apiUrl($this->tenant, '/bookings'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['service_id', 'starts_at', 'customer_name', 'customer_phone']);

    $this->withToken($this->writeToken)
        ->postJson(apiUrl($this->tenant, '/bookings'), bookingPayload(['customer_phone' => '12']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('customer_phone');

    $this->withToken($this->writeToken)
        ->postJson(apiUrl($this->tenant, '/bookings'), bookingPayload(['customer_email' => 'nope']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('customer_email');
});

it('refuses a service or staff member from another tenant', function () {
    [$theirService, $theirStaff] = app(TenantContext::class)->runAs(
        Tenant::factory()->create(['timezone' => 'Asia/Riyadh']),
        function () {
            $service = Service::factory()->lasting(60)->create();
            $staff = Staff::factory()->create();
            $staff->services()->attach($service);

            return [$service, $staff];
        }
    );

    $this->withToken($this->writeToken)
        ->postJson(apiUrl($this->tenant, '/bookings'), bookingPayload(['service_id' => $theirService->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('service_id');

    $this->withToken($this->writeToken)
        ->postJson(apiUrl($this->tenant, '/bookings'), bookingPayload(['staff_id' => $theirStaff->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('staff_id');

    expect(Booking::withoutTenancy()->count())->toBe(0);
});

it('needs the write ability to create', function () {
    $this->withToken($this->readToken)
        ->postJson(apiUrl($this->tenant, '/bookings'), bookingPayload())
        ->assertForbidden()
        ->assertJsonPath('message', 'This token is not allowed to do that.');

    betweenRequests($this->tenant);

    expect(Booking::count())->toBe(0);
});

it('needs the write ability to cancel', function () {
    $booking = Booking::factory()->for($this->haircut, 'service')->for($this->sara, 'staff')->startingAt($this->ten)->create();

    $this->withToken($this->readToken)
        ->deleteJson(apiUrl($this->tenant, "/bookings/{$booking->reference}"))
        ->assertForbidden();

    betweenRequests($this->tenant);

    expect($booking->refresh()->isConfirmed())->toBeTrue();
});

it('lists bookings by date range, staff and status', function () {
    Booking::factory()->for($this->haircut, 'service')->for($this->sara, 'staff')->startingAt($this->ten)->create();
    Booking::factory()->for($this->haircut, 'service')->for($this->sara, 'staff')
        ->startingAt(CarbonImmutable::parse('2026-10-12 10:00', 'Asia/Riyadh'))->create();
    $cancelled = Booking::factory()->for($this->haircut, 'service')->for($this->sara, 'staff')
        ->startingAt(CarbonImmutable::parse('2026-10-05 12:00', 'Asia/Riyadh'))->cancelled()->create();

    $this->withToken($this->readToken)
        ->getJson(apiUrl($this->tenant, '/bookings'))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.timezone', 'Asia/Riyadh');

    $this->withToken($this->readToken)
        ->getJson(apiUrl($this->tenant, '/bookings?from=2026-10-05T00:00:00Z&to=2026-10-06T00:00:00Z'))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $this->withToken($this->readToken)
        ->getJson(apiUrl($this->tenant, '/bookings?status=cancelled'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.reference', $cancelled->reference);

    $this->withToken($this->readToken)
        ->getJson(apiUrl($this->tenant, '/bookings?per_page=1'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 3);

    $this->withToken($this->readToken)
        ->getJson(apiUrl($this->tenant, '/bookings?status=elsewhere'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('shows and cancels one booking by its reference', function () {
    Mail::fake();

    $booking = Booking::factory()->for($this->haircut, 'service')->for($this->sara, 'staff')
        ->startingAt($this->ten)->create(['customer_email' => 'basma@example.com']);

    $this->withToken($this->readToken)
        ->getJson(apiUrl($this->tenant, "/bookings/{$booking->reference}"))
        ->assertOk()
        ->assertJsonPath('data.reference', $booking->reference)
        ->assertJsonPath('data.service.name', 'Haircut');

    betweenRequests();

    $this->withToken($this->writeToken)
        ->deleteJson(apiUrl($this->tenant, "/bookings/{$booking->reference}"), ['reason' => 'Customer called'])
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancellation_reason', 'Customer called');

    betweenRequests($this->tenant);

    expect($booking->refresh())->status->toBe(BookingStatus::Cancelled)->slot_lock->toBeNull();

    Mail::assertSent(CustomerNotificationMail::class, fn ($mail) => $mail->notification instanceof BookingCancelledNotification);
});

it('answers 422 when a booking can no longer be cancelled', function () {
    $booking = Booking::factory()->for($this->haircut, 'service')->for($this->sara, 'staff')->startingAt($this->ten)->cancelled()->create();

    $this->withToken($this->writeToken)
        ->deleteJson(apiUrl($this->tenant, "/bookings/{$booking->reference}"))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'This booking has already been cancelled.');
});

it('cannot read or cancel another tenant\'s booking, even knowing its reference', function () {
    $theirs = app(TenantContext::class)->runAs(
        Tenant::factory()->create(['timezone' => 'Asia/Riyadh']),
        function () {
            $service = Service::factory()->lasting(30)->create();
            $staff = Staff::factory()->create();

            return Booking::factory()->for($service, 'service')->for($staff, 'staff')->create();
        }
    );

    $this->withToken($this->readToken)
        ->getJson(apiUrl($this->tenant, "/bookings/{$theirs->reference}"))
        ->assertNotFound();

    betweenRequests();

    $this->withToken($this->writeToken)
        ->deleteJson(apiUrl($this->tenant, "/bookings/{$theirs->reference}"))
        ->assertNotFound();

    expect(Booking::withoutTenancy()->find($theirs->getKey())->isConfirmed())->toBeTrue();
});
