<?php

use App\Actions\Booking\CreateBooking;
use App\Data\BookingRequestData;
use App\Enums\BookingStatus;
use App\Enums\Weekday;
use App\Livewire\Booking\ManageBooking;
use App\Mail\CustomerNotificationMail;
use App\Messaging\BookingCancelledNotification;
use App\Messaging\BookingRescheduledNotification;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Livewire\Livewire;

// 2026-10-05 is a Monday; tenant in Riyadh (UTC+3).
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00', 'UTC'));

    $this->tenant = bindTenant(Tenant::factory()->create(['name' => 'Acme Salon', 'slug' => 'acme', 'timezone' => 'Asia/Riyadh']));
    $this->service = Service::factory()->lasting(60)->create(['name' => 'Haircut']);
    $this->sara = Staff::factory()->create(['name' => 'Sara']);
    $this->sara->services()->attach($this->service);
    Schedule::factory()->for($this->sara)->on(Weekday::Monday, '09:00', '13:00')->create();

    $this->booking = Booking::factory()
        ->for($this->service, 'service')
        ->for($this->sara, 'staff')
        ->startingAt(CarbonImmutable::parse('2026-10-05 10:00', 'Asia/Riyadh'))
        ->create(['customer_name' => 'Basma', 'customer_email' => 'basma@example.com']);
});

it('shows the booking behind its signed link', function () {
    $this->get($this->booking->manageUrl())
        ->assertOk()
        ->assertSeeLivewire(ManageBooking::class)
        ->assertSee($this->booking->reference)
        ->assertSee('Haircut')
        ->assertSee('Sara')
        ->assertSee('Monday 5 October 2026')
        ->assertSee('Asia/Riyadh');
});

it('refuses a link whose signature is missing or tampered with', function () {
    $url = $this->booking->manageUrl();

    $this->get(Str::before($url, '?'))->assertForbidden();
    $this->get($url.'x')->assertForbidden();
});

it('does not reveal a booking from another tenant or an unknown token', function () {
    $other = Tenant::factory()->create(['slug' => 'other', 'timezone' => 'Asia/Riyadh']);
    $theirs = app(TenantContext::class)->runAs($other, function () {
        $service = Service::factory()->lasting(30)->create();
        $staff = Staff::factory()->create();

        return Booking::factory()->for($service, 'service')->for($staff, 'staff')->create();
    });

    // Their token, signed for our host: the lookup runs inside our tenant scope.
    $this->get(URL::signedRoute('tenant.booking.manage', ['tenant' => 'acme', 'token' => $theirs->cancel_token]))
        ->assertNotFound();

    $this->get(URL::signedRoute('tenant.booking.manage', ['tenant' => 'acme', 'token' => str_repeat('z', 40)]))
        ->assertNotFound();
});

it('lets the customer cancel with a reason and tells them so', function () {
    Livewire::test(ManageBooking::class, ['token' => $this->booking->cancel_token])
        ->set('reason', 'Away that week')
        ->call('cancel')
        ->assertHasNoErrors()
        ->assertSee('has been cancelled');

    expect($this->booking->refresh())
        ->status->toBe(BookingStatus::Cancelled)
        ->slot_lock->toBeNull()
        ->cancellation_reason->toBe('Away that week')
        ->and($this->booking->cancelled_at)->not->toBeNull();
});

it('emails the customer that the booking was cancelled', function () {
    Mail::fake();

    Livewire::test(ManageBooking::class, ['token' => $this->booking->cancel_token])->call('cancel');

    Mail::assertSent(
        CustomerNotificationMail::class,
        fn ($mail) => $mail->notification instanceof BookingCancelledNotification
            && str_contains($mail->render(), $this->booking->reference)
    );
});

it('frees the slot so someone else can take it', function () {
    $start = $this->booking->starts_at;

    Livewire::test(ManageBooking::class, ['token' => $this->booking->cancel_token])->call('cancel');

    $replacement = app(CreateBooking::class)->handle(new BookingRequestData(
        serviceId: $this->service->id,
        staffId: $this->sara->id,
        start: $start,
        customerName: 'Next Customer',
        customerPhone: '+966501234567',
    ));

    expect($replacement->exists)->toBeTrue()
        ->and(Booking::confirmed()->count())->toBe(1);
});

it('refuses a second cancellation', function () {
    $component = Livewire::test(ManageBooking::class, ['token' => $this->booking->cancel_token]);

    $component->call('cancel')->assertSee('has been cancelled');
    $component->call('cancel')->assertSee('already been cancelled');

    expect(Booking::count())->toBe(1);
});

it('will not let the customer change a booking inside the notice period', function () {
    // Two hours' notice by default; travel to 90 minutes before the appointment.
    $this->travelTo($this->booking->starts_at->subMinutes(90));

    $component = Livewire::test(ManageBooking::class, ['token' => $this->booking->cancel_token])
        ->assertSee('Changes are possible up to')
        ->assertDontSee('Change time');

    $component->call('cancel')->assertSee('too close to its start time');

    expect($this->booking->refresh()->isConfirmed())->toBeTrue();
});

it('lets the customer move the booking to a free slot with the same staff member', function () {
    $component = Livewire::test(ManageBooking::class, ['token' => $this->booking->cancel_token])
        ->call('startReschedule')
        ->set('date', '2026-10-05')
        ->assertSee('09:00')
        ->assertSee('12:00');

    // The booking's own slot stays on offer, and an overlapping time is reachable.
    $component->call('chooseSlot', CarbonImmutable::parse('2026-10-05 12:00', 'Asia/Riyadh')->utc()->toIso8601String())
        ->assertHasNoErrors()
        ->assertSee('has been moved');

    $moved = $this->booking->refresh();

    expect($moved->starts_at->setTimezone('Asia/Riyadh')->format('H:i'))->toBe('12:00')
        ->and($moved->ends_at->setTimezone('Asia/Riyadh')->format('H:i'))->toBe('13:00')
        ->and($moved->isConfirmed())->toBeTrue()
        ->and(Booking::count())->toBe(1);
});

it('tells the customer to pick again when the new slot is taken meanwhile', function () {
    $component = Livewire::test(ManageBooking::class, ['token' => $this->booking->cancel_token])
        ->call('startReschedule')
        ->set('date', '2026-10-05');

    $noon = CarbonImmutable::parse('2026-10-05 12:00', 'Asia/Riyadh')->utc();

    Booking::factory()->for($this->service, 'service')->for($this->sara, 'staff')->startingAt($noon)->create();

    $component->call('chooseSlot', $noon->toIso8601String())->assertSee('just taken');

    expect($this->booking->refresh()->starts_at->setTimezone('Asia/Riyadh')->format('H:i'))->toBe('10:00');
});

it('emails the customer the new time after a move', function () {
    Mail::fake();

    Livewire::test(ManageBooking::class, ['token' => $this->booking->cancel_token])
        ->call('startReschedule')
        ->set('date', '2026-10-05')
        ->call('chooseSlot', CarbonImmutable::parse('2026-10-05 12:00', 'Asia/Riyadh')->utc()->toIso8601String());

    Mail::assertSent(
        CustomerNotificationMail::class,
        function ($mail) {
            $body = $mail->render();

            return $mail->notification instanceof BookingRescheduledNotification
                && str_contains($body, '10:00')   // previously
                && str_contains($body, '12:00');  // now
        }
    );
});

it('ignores a move to the time the booking already has', function () {
    Mail::fake();

    Livewire::test(ManageBooking::class, ['token' => $this->booking->cancel_token])
        ->call('startReschedule')
        ->set('date', '2026-10-05')
        ->call('chooseSlot', $this->booking->starts_at->toIso8601String())
        ->assertHasNoErrors();

    expect($this->booking->refresh()->starts_at->setTimezone('Asia/Riyadh')->format('H:i'))->toBe('10:00');

    Mail::assertNothingSent();
});

it('offers nothing to change once the booking is cancelled', function () {
    $this->booking->cancel('By phone');

    Livewire::test(ManageBooking::class, ['token' => $this->booking->cancel_token])
        ->assertSee('This booking is cancelled')
        ->assertSee('By phone')
        ->assertDontSee('Change time');
});
