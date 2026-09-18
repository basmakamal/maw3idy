<?php

use App\Enums\BookingStatus;
use App\Livewire\Dashboard\Calendar;
use App\Mail\CustomerNotificationMail;
use App\Messaging\BookingCancelledNotification;
use App\Models\Booking;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

// Thursday 2026-10-01 in Riyadh; that week runs Sunday 27 Sep to Saturday 3 Oct.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 06:00', 'UTC'));

    $this->tenant = bindTenant(Tenant::factory()->create(['timezone' => 'Asia/Riyadh']));
    $this->owner = User::factory()->owner()->create();
    $this->member = User::factory()->create();

    $this->haircut = Service::factory()->lasting(60)->create(['name' => 'Haircut', 'price' => '80.00']);
    $this->sara = Staff::factory()->create(['name' => 'Sara']);
    $this->omar = Staff::factory()->create(['name' => 'Omar']);

    $this->today = Booking::factory()->for($this->haircut, 'service')->for($this->sara, 'staff')
        ->startingAt(CarbonImmutable::parse('2026-10-01 14:00', 'Asia/Riyadh'))
        ->create(['customer_name' => 'Layla', 'customer_phone' => '+966501112222', 'customer_email' => 'layla@example.com']);

    $this->tomorrow = Booking::factory()->for($this->haircut, 'service')->for($this->omar, 'staff')
        ->startingAt(CarbonImmutable::parse('2026-10-02 11:00', 'Asia/Riyadh'))
        ->create(['customer_name' => 'Khalid']);

    $this->nextWeek = Booking::factory()->for($this->haircut, 'service')->for($this->sara, 'staff')
        ->startingAt(CarbonImmutable::parse('2026-10-06 11:00', 'Asia/Riyadh'))
        ->create(['customer_name' => 'Reem']);
});

it('renders the calendar page for any user of the tenant', function () {
    $this->actingAs($this->member)
        ->get(tenantUrl($this->tenant, '/calendar'))
        ->assertOk()
        ->assertSeeLivewire(Calendar::class)
        ->assertSee('Asia/Riyadh');
});

it('shows the current week by default and not bookings from other weeks', function () {
    Livewire::actingAs($this->owner)
        ->test(Calendar::class)
        ->assertSet('mode', 'week')
        ->assertSee('Layla')
        ->assertSee('Khalid')
        ->assertDontSee('Reem');
});

it('moves a week at a time and back to today', function () {
    $component = Livewire::actingAs($this->owner)->test(Calendar::class);

    $component->call('next')->assertSee('Reem')->assertDontSee('Layla');
    $component->call('previous')->assertSee('Layla')->assertDontSee('Reem');
    $component->call('next')->call('goToToday')->assertSee('Layla');
});

it('shows one day at a time with the customer\'s details', function () {
    Livewire::actingAs($this->owner)
        ->test(Calendar::class)
        ->call('showDay', '2026-10-01')
        ->assertSet('mode', 'day')
        ->assertSee('Thursday 1 October 2026')
        ->assertSee('14:00')
        ->assertSee('15:00')
        ->assertSee('Layla')
        ->assertSee('+966501112222')
        ->assertSee($this->today->reference)
        ->assertDontSee('Khalid');
});

it('filters by staff member', function () {
    $component = Livewire::actingAs($this->owner)->test(Calendar::class);

    $component->set('staffId', $this->sara->id)->assertSee('Layla')->assertDontSee('Khalid');
    $component->set('staffId', $this->omar->id)->assertSee('Khalid')->assertDontSee('Layla');
    $component->set('staffId', null)->assertSee('Layla')->assertSee('Khalid');
});

it('hides cancelled bookings unless asked for them', function () {
    $this->today->cancel('Customer called');

    $component = Livewire::actingAs($this->owner)->test(Calendar::class)->assertDontSee('Layla');

    $component->set('includeCancelled', true)->assertSee('Layla');
});

it('lets staff cancel a booking and tells the customer', function () {
    Mail::fake();

    Livewire::actingAs($this->member)
        ->test(Calendar::class)
        ->call('showDay', '2026-10-01')
        ->call('cancel', $this->today->id)
        ->assertHasNoErrors()
        ->assertSee('cancelled');

    expect($this->today->refresh())
        ->status->toBe(BookingStatus::Cancelled)
        ->slot_lock->toBeNull()
        ->cancellation_reason->toBe('Cancelled by the business');

    Mail::assertSent(
        CustomerNotificationMail::class,
        fn (CustomerNotificationMail $mail) => $mail->notification instanceof BookingCancelledNotification
    );
});

it('reports a booking that can no longer be cancelled', function () {
    $this->today->cancel('Already done');

    Livewire::actingAs($this->member)
        ->test(Calendar::class)
        ->set('includeCancelled', true)
        ->call('cancel', $this->today->id)
        ->assertSee('could not be cancelled');
});

it('never shows or cancels another tenant\'s bookings', function () {
    $other = Tenant::factory()->create(['timezone' => 'Asia/Riyadh']);
    $theirs = app(TenantContext::class)->runAs($other, function () {
        $service = Service::factory()->lasting(30)->create();
        $staff = Staff::factory()->create();

        return Booking::factory()->for($service, 'service')->for($staff, 'staff')
            ->startingAt(CarbonImmutable::parse('2026-10-01 15:00', 'Asia/Riyadh'))
            ->create(['customer_name' => 'Not Ours']);
    });

    $component = Livewire::actingAs($this->owner)->test(Calendar::class)->assertDontSee('Not Ours');

    // The tenant scope means the row is not findable at all, so it never reaches the policy.
    expect(fn () => $component->call('cancel', $theirs->getKey()))->toThrow(ModelNotFoundException::class);

    expect($theirs->refresh()->isConfirmed())->toBeTrue();
});

it('ignores a staff filter from the query string that belongs to another tenant', function () {
    $foreign = app(TenantContext::class)
        ->runAs(Tenant::factory()->create(), fn () => Staff::factory()->create());

    Livewire::actingAs($this->owner)
        ->withQueryParams(['staff' => $foreign->getKey()])
        ->test(Calendar::class)
        ->assertSet('staffId', null)
        ->assertSee('Layla');
});

it('falls back to today when the date in the query string is nonsense', function () {
    Livewire::actingAs($this->owner)
        ->withQueryParams(['date' => 'whenever', 'view' => 'sideways'])
        ->test(Calendar::class)
        ->assertSet('date', '2026-10-01')
        ->assertSet('mode', 'week');
});

it('sends guests to the login page', function () {
    $this->get(tenantUrl($this->tenant, '/calendar'))
        ->assertRedirect(tenantUrl($this->tenant, '/login'));
});
