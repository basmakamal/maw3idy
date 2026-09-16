<?php

use App\Enums\Weekday;
use App\Livewire\Booking\BookingWizard;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

// 2026-10-05 is a Monday; tenant in Riyadh.
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00', 'UTC'));

    $this->tenant = bindTenant(Tenant::factory()->create(['name' => 'Acme Salon', 'timezone' => 'Asia/Riyadh']));
    $this->haircut = Service::factory()->lasting(30)->create(['name' => 'Haircut', 'price' => '80.00']);
    $this->colour = Service::factory()->lasting(60)->create(['name' => 'Hair colour']);
    Service::factory()->inactive()->create(['name' => 'Retired service']);
    Service::factory()->create(['name' => 'Nobody offers this']);

    $this->sara = Staff::factory()->create(['name' => 'Sara']);
    $this->sara->services()->attach([$this->haircut->id, $this->colour->id]);
    Schedule::factory()->for($this->sara)->on(Weekday::Monday, '09:00', '11:00')->create();

    $this->tenAm = CarbonImmutable::parse('2026-10-05 10:00', 'Asia/Riyadh')->utc()->toIso8601String();
});

it('serves the booking page publicly on the tenant subdomain', function () {
    $this->get(tenantUrl($this->tenant, '/book'))
        ->assertOk()
        ->assertSeeLivewire(BookingWizard::class)
        ->assertSee('Acme Salon')
        ->assertSee('Haircut')
        ->assertSee('Hair colour')
        ->assertDontSee('Retired service')
        ->assertDontSee('Nobody offers this');

    $this->get(centralUrl('/book'))->assertNotFound();
});

it('walks a customer from service to confirmation', function () {
    Livewire::test(BookingWizard::class)
        ->assertSet('step', 1)
        ->call('chooseService', $this->haircut->id)
        ->assertSet('step', 2)
        ->assertSee('Sara')
        ->assertSee('Anyone available')
        ->call('chooseStaff', null)
        ->assertSet('step', 3)
        ->set('date', '2026-10-05')
        ->assertSee('09:00')
        ->assertSee('10:30')
        ->assertSee('Asia/Riyadh')
        ->call('chooseSlot', $this->tenAm)
        ->assertSet('step', 4)
        ->assertSee('Monday 5 October 2026')
        ->set('customerName', 'Basma Kamal')
        ->set('customerPhone', '050 123 4567')
        ->set('customerEmail', '')
        ->call('confirm')
        ->assertHasNoErrors()
        ->assertRedirect(tenantUrl($this->tenant, '/book/confirmed'));

    $booking = Booking::sole();

    expect($booking)
        ->customer_name->toBe('Basma Kamal')
        ->customer_phone->toBe('0501234567')
        ->customer_email->toBeNull()
        ->staff_id->toBe($this->sara->id)
        ->service_id->toBe($this->haircut->id)
        ->and($booking->starts_at->toIso8601String())->toBe($this->tenAm);

    $this->get(tenantUrl($this->tenant, '/book/confirmed'))
        ->assertOk()
        ->assertSee($booking->reference)
        ->assertSee('Basma Kamal')
        ->assertSee('Sara')
        ->assertSee('10:00')
        ->assertSee('Asia/Riyadh');
});

it('rejects ids and times the browser made up', function () {
    $component = Livewire::test(BookingWizard::class);

    $component->call('chooseService', 999_999)->assertHasErrors('serviceId')->assertSet('step', 1);

    $inactive = Service::query()->where('name', 'Retired service')->sole();
    $component->call('chooseService', $inactive->id)->assertHasErrors('serviceId')->assertSet('step', 1);

    $component->call('chooseService', $this->haircut->id)->assertSet('step', 2);

    $stranger = Staff::factory()->create();
    $component->call('chooseStaff', $stranger->id)->assertHasErrors('staffId')->assertSet('step', 2);

    $component->call('chooseStaff', $this->sara->id)->assertSet('step', 3)->set('date', '2026-10-05');

    $offGrid = CarbonImmutable::parse('2026-10-05 10:15', 'Asia/Riyadh')->utc()->toIso8601String();
    $component->call('chooseSlot', $offGrid)->assertHasErrors('slot')->assertSet('step', 3)->assertSet('slot', null);
});

it('keeps the date inside the booking window', function () {
    $component = Livewire::test(BookingWizard::class)
        ->call('chooseService', $this->haircut->id)
        ->call('chooseStaff', null);

    $component->set('date', '2026-09-30')->assertHasErrors('date');
    $component->set('date', '2027-01-01')->assertHasErrors('date');
    $component->set('date', 'not-a-date')->assertHasErrors('date');
    $component->set('date', '2026-10-05')->assertHasNoErrors();
});

it('validates the customer details', function () {
    Livewire::test(BookingWizard::class)
        ->call('chooseService', $this->haircut->id)
        ->call('chooseStaff', $this->sara->id)
        ->set('date', '2026-10-05')
        ->call('chooseSlot', $this->tenAm)
        ->set('customerName', '')
        ->set('customerPhone', '12')
        ->set('customerEmail', 'not-an-email')
        ->call('confirm')
        ->assertHasErrors(['customerName' => 'required', 'customerPhone', 'customerEmail']);

    expect(Booking::count())->toBe(0);
});

it('sends the customer back to the times when the slot is taken meanwhile', function () {
    $component = Livewire::test(BookingWizard::class)
        ->call('chooseService', $this->haircut->id)
        ->call('chooseStaff', $this->sara->id)
        ->set('date', '2026-10-05')
        ->call('chooseSlot', $this->tenAm)
        ->set('customerName', 'Late Customer')
        ->set('customerPhone', '0501234567');

    // Someone else books Sara at 10:00 while our customer is typing.
    Booking::factory()->for($this->haircut, 'service')->for($this->sara, 'staff')
        ->startingAt(CarbonImmutable::parse($this->tenAm))
        ->create();

    $component->call('confirm')
        ->assertHasNoErrors()
        ->assertNoRedirect()
        ->assertSet('step', 3)
        ->assertSet('slot', null)
        ->assertSee('just taken');

    expect(Booking::count())->toBe(1);
});

it('limits how many bookings one connection can make per hour', function () {
    // Ten bookings already made from this address within the hour.
    foreach (range(1, 10) as $i) {
        RateLimiter::hit('book:'.$this->tenant->id.':127.0.0.1', 3600);
    }

    Livewire::test(BookingWizard::class)
        ->call('chooseService', $this->haircut->id)
        ->call('chooseStaff', $this->sara->id)
        ->set('date', '2026-10-05')
        ->call('chooseSlot', $this->tenAm)
        ->set('customerName', 'Repeat')
        ->set('customerPhone', '0501234567')
        ->call('confirm')
        ->assertHasErrors('customerPhone')
        ->assertNoRedirect();

    expect(Booking::count())->toBe(0);
});

it('shows nothing on the confirmation page without a fresh booking in the session', function () {
    $this->get(tenantUrl($this->tenant, '/book/confirmed'))->assertNotFound();
});
