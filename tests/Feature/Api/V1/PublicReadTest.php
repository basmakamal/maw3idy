<?php

use App\Enums\Weekday;
use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;

// 2026-10-05 is a Monday; tenant in Riyadh (UTC+3).
beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00', 'UTC'));

    $this->tenant = bindTenant(Tenant::factory()->create(['slug' => 'acme', 'timezone' => 'Asia/Riyadh']));

    $this->haircut = Service::factory()->lasting(60)->create(['name' => 'Haircut', 'price' => '80.00', 'description' => 'Wash and cut.']);
    $this->sara = Staff::factory()->create(['name' => 'Sara']);
    $this->sara->services()->attach($this->haircut);
    Schedule::factory()->for($this->sara)->on(Weekday::Monday, '09:00', '12:00')->create();

    Service::factory()->inactive()->create(['name' => 'Retired']);
    Service::factory()->create(['name' => 'Nobody offers this']);
});

it('lists the bookable catalogue without a token', function () {
    $this->getJson(apiUrl($this->tenant, '/services'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Haircut')
        ->assertJsonPath('data.0.duration_minutes', 60)
        ->assertJsonPath('data.0.price', '80.00')
        ->assertJsonPath('data.0.currency', 'SAR')
        ->assertJsonPath('data.0.staff.0.name', 'Sara')
        ->assertJsonPath('meta.timezone', 'Asia/Riyadh')
        ->assertJsonMissing(['name' => 'Retired'])
        ->assertJsonMissing(['name' => 'Nobody offers this']);
});

it('hides a service that is not bookable', function () {
    $retired = Service::query()->where('name', 'Retired')->sole();

    $this->getJson(apiUrl($this->tenant, "/services/{$retired->id}"))->assertNotFound();
    $this->getJson(apiUrl($this->tenant, "/services/{$this->haircut->id}"))->assertOk()->assertJsonPath('data.name', 'Haircut');
});

it('answers availability in UTC with the tenant timezone in the meta', function () {
    $this->getJson(apiUrl($this->tenant, "/services/{$this->haircut->id}/availability?date=2026-10-05"))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.starts_at', '2026-10-05T06:00:00+00:00')
        ->assertJsonPath('data.0.ends_at', '2026-10-05T07:00:00+00:00')
        ->assertJsonPath('data.0.staff_ids', [$this->sara->id])
        ->assertJsonPath('meta.timezone', 'Asia/Riyadh')
        ->assertJsonPath('meta.date', '2026-10-05')
        ->assertJsonPath('meta.duration_minutes', 60);
});

it('leaves out slots that are already booked', function () {
    Booking::factory()->for($this->haircut, 'service')->for($this->sara, 'staff')
        ->startingAt(CarbonImmutable::parse('2026-10-05 10:00', 'Asia/Riyadh'))
        ->create();

    $this->getJson(apiUrl($this->tenant, "/services/{$this->haircut->id}/availability?date=2026-10-05"))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissing(['starts_at' => '2026-10-05T07:00:00+00:00']);
});

it('can be asked for one staff member only', function () {
    $omar = Staff::factory()->create(['name' => 'Omar']);
    $omar->services()->attach($this->haircut);
    Schedule::factory()->for($omar)->on(Weekday::Monday, '14:00', '16:00')->create();

    $this->getJson(apiUrl($this->tenant, "/services/{$this->haircut->id}/availability?date=2026-10-05&staff_id={$omar->id}"))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.staff_id', $omar->id)
        ->assertJsonPath('data.0.staff_ids', [$omar->id]);
});

it('validates the date and the staff member', function () {
    $url = apiUrl($this->tenant, "/services/{$this->haircut->id}/availability");

    $this->getJson($url)->assertUnprocessable()->assertJsonValidationErrors('date');
    $this->getJson($url.'?date=05-10-2026')->assertUnprocessable()->assertJsonValidationErrors('date');
    $this->getJson($url.'?date=2026-09-30')->assertUnprocessable()->assertJsonValidationErrors('date');
    $this->getJson($url.'?date=2027-01-01')->assertUnprocessable()->assertJsonValidationErrors('date');
    $this->getJson($url.'?date=2026-10-05&staff_id=999999')->assertUnprocessable()->assertJsonValidationErrors('staff_id');
});

it('never answers with another tenant\'s staff or services', function () {
    [$theirService, $theirStaff] = app(TenantContext::class)->runAs(
        Tenant::factory()->create(['timezone' => 'Asia/Riyadh']),
        function () {
            $service = Service::factory()->lasting(30)->create(['name' => 'Theirs']);
            $staff = Staff::factory()->create();
            $staff->services()->attach($service);

            return [$service, $staff];
        }
    );

    $this->getJson(apiUrl($this->tenant, "/services/{$theirService->id}"))->assertNotFound();
    $this->getJson(apiUrl($this->tenant, "/services/{$theirService->id}/availability?date=2026-10-05"))->assertNotFound();

    $this->getJson(apiUrl($this->tenant, "/services/{$this->haircut->id}/availability?date=2026-10-05&staff_id={$theirStaff->id}"))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('staff_id');
});

it('throttles the public availability endpoint', function () {
    $url = apiUrl($this->tenant, "/services/{$this->haircut->id}/availability?date=2026-10-05");

    foreach (range(1, 60) as $call) {
        $this->getJson($url)->assertOk();
    }

    $this->getJson($url)->assertStatus(429);
});
