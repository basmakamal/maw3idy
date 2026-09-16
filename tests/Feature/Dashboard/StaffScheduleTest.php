<?php

use App\Enums\Weekday;
use App\Livewire\Staff\StaffSchedule;
use App\Models\Schedule;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\TimeOff;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-01 09:00', 'UTC'));

    $this->tenant = bindTenant(Tenant::factory()->create(['timezone' => 'Asia/Riyadh']));
    $this->owner = User::factory()->owner()->create();
    $this->member = User::factory()->create();
    $this->sara = Staff::factory()->create(['name' => 'Sara']);
    Schedule::factory()->for($this->sara)->on(Weekday::Monday, '09:00', '17:00')->create();
});

it('shows a staff member\'s schedule page, but never another tenant\'s', function () {
    $this->actingAs($this->owner)
        ->get(tenantUrl($this->tenant, "/staff/{$this->sara->id}/schedule"))
        ->assertOk()
        ->assertSeeLivewire(StaffSchedule::class)
        ->assertSee('Sara')
        ->assertSee('Asia/Riyadh')
        ->assertSee('Save hours');

    $this->actingAs($this->member)
        ->get(tenantUrl($this->tenant, "/staff/{$this->sara->id}/schedule"))
        ->assertOk()
        ->assertDontSee('Save hours');

    // Route model binding resolves through the tenant scope: a foreign id is a 404, not a 403.
    $foreign = app(TenantContext::class)->runAs(Tenant::factory()->create(), fn () => Staff::factory()->create());

    $this->actingAs($this->owner)
        ->get(tenantUrl($this->tenant, "/staff/{$foreign->id}/schedule"))
        ->assertNotFound();
});

it('loads the current hours into the form', function () {
    Livewire::actingAs($this->owner)
        ->test(StaffSchedule::class, ['staff' => $this->sara])
        ->assertSet('days.'.Weekday::Monday->value.'.working', true)
        ->assertSet('days.'.Weekday::Monday->value.'.start', '09:00')
        ->assertSet('days.'.Weekday::Monday->value.'.end', '17:00')
        ->assertSet('days.'.Weekday::Friday->value.'.working', false);
});

it('replaces the weekly hours when the owner saves', function () {
    Livewire::actingAs($this->owner)
        ->test(StaffSchedule::class, ['staff' => $this->sara])
        ->set('days.'.Weekday::Monday->value.'.working', false)
        ->set('days.'.Weekday::Sunday->value.'.working', true)
        ->set('days.'.Weekday::Sunday->value.'.start', '10:00')
        ->set('days.'.Weekday::Sunday->value.'.end', '14:00')
        ->set('days.'.Weekday::Tuesday->value.'.working', true)
        ->set('days.'.Weekday::Tuesday->value.'.start', '13:00')
        ->set('days.'.Weekday::Tuesday->value.'.end', '20:00')
        ->call('saveHours')
        ->assertHasNoErrors()
        ->assertSee('Working hours saved');

    $hours = $this->sara->schedules()->orderBy('weekday')->get()
        ->map(fn (Schedule $s) => [$s->weekday->name, substr($s->start_time, 0, 5), substr($s->end_time, 0, 5)])
        ->all();

    expect($hours)->toBe([['Tuesday', '13:00', '20:00'], ['Sunday', '10:00', '14:00']]);
});

it('rejects a working day whose end is not after its start and keeps the old hours', function () {
    Livewire::actingAs($this->owner)
        ->test(StaffSchedule::class, ['staff' => $this->sara])
        ->set('days.'.Weekday::Monday->value.'.start', '17:00')
        ->set('days.'.Weekday::Monday->value.'.end', '09:00')
        ->call('saveHours')
        ->assertHasErrors(['days.'.Weekday::Monday->value.'.end']);

    expect($this->sara->schedules()->sole()->start_time)->toStartWith('09:00');
});

it('adds time off in the tenant timezone and stores it as UTC', function () {
    Livewire::actingAs($this->owner)
        ->test(StaffSchedule::class, ['staff' => $this->sara])
        ->set('offStart', '2026-10-05T12:00')
        ->set('offEnd', '2026-10-05T13:30')
        ->set('offReason', 'Lunch with a client')
        ->call('addTimeOff')
        ->assertHasNoErrors()
        ->assertSet('offStart', '')
        ->assertSee('Lunch with a client');

    $absence = $this->sara->timeOff()->sole();

    expect(DB::table('time_off')->where('id', $absence->id)->value('starts_at'))->toBe('2026-10-05 09:00:00')
        ->and(DB::table('time_off')->where('id', $absence->id)->value('ends_at'))->toBe('2026-10-05 10:30:00')
        ->and($absence->reason)->toBe('Lunch with a client');
});

it('rejects time off that ends before it starts or is malformed', function () {
    $component = Livewire::actingAs($this->owner)->test(StaffSchedule::class, ['staff' => $this->sara]);

    $component->set('offStart', '2026-10-05T13:00')->set('offEnd', '2026-10-05T12:00')->call('addTimeOff')->assertHasErrors(['offEnd']);
    $component->set('offStart', 'yesterday')->set('offEnd', '2026-10-05T12:00')->call('addTimeOff')->assertHasErrors(['offStart']);

    expect(TimeOff::count())->toBe(0);
});

it('removes time off', function () {
    $absence = TimeOff::factory()->for($this->sara)->between(
        CarbonImmutable::parse('2026-10-10 00:00', 'Asia/Riyadh'),
        CarbonImmutable::parse('2026-10-11 00:00', 'Asia/Riyadh'),
    )->create();

    Livewire::actingAs($this->owner)
        ->test(StaffSchedule::class, ['staff' => $this->sara])
        ->assertSee('Sat 10 Oct 2026 00:00')
        ->call('removeTimeOff', $absence->id)
        ->assertDontSee('Sat 10 Oct 2026 00:00');

    expect(TimeOff::count())->toBe(0);
});

it('forbids members from changing hours or time off', function () {
    Livewire::actingAs($this->member)->test(StaffSchedule::class, ['staff' => $this->sara])->call('saveHours')->assertForbidden();
    Livewire::actingAs($this->member)->test(StaffSchedule::class, ['staff' => $this->sara])
        ->set('offStart', '2026-10-05T12:00')->set('offEnd', '2026-10-05T13:00')->call('addTimeOff')->assertForbidden();

    expect($this->sara->schedules()->count())->toBe(1)->and(TimeOff::count())->toBe(0);
});
