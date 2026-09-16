<?php

use App\Livewire\Services\ServiceIndex;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = bindTenant(Tenant::factory()->create());
    $this->owner = User::factory()->owner()->create();
    $this->member = User::factory()->create();
    $this->sara = Staff::factory()->create(['name' => 'Sara']);
});

it('renders the services page with the catalogue for any user', function () {
    Service::factory()->create(['name' => 'Haircut', 'price' => '80.00']);

    $this->actingAs($this->member)
        ->get(tenantUrl($this->tenant, '/services'))
        ->assertOk()
        ->assertSeeLivewire(ServiceIndex::class)
        ->assertSee('Haircut')
        ->assertSee('80.00')
        ->assertDontSee('Add service');

    $this->actingAs($this->owner)
        ->get(tenantUrl($this->tenant, '/services'))
        ->assertSee('Add service');
});

it('lets the owner add a service offered by chosen staff', function () {
    Livewire::actingAs($this->owner)
        ->test(ServiceIndex::class)
        ->call('create')
        ->assertSet('editing', true)
        ->set('form.name', 'Beard trim')
        ->set('form.description', 'Hot towel included')
        ->set('form.duration_minutes', '20')
        ->set('form.buffer_after_minutes', '5')
        ->set('form.price', '45')
        ->set('form.active', true)
        ->set('form.staffIds', [(string) $this->sara->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editing', false)
        ->assertSee('Beard trim');

    $service = Service::query()->where('name', 'Beard trim')->sole();

    expect($service)
        ->duration_minutes->toBe(20)
        ->buffer_after_minutes->toBe(5)
        ->price->toBe('45.00')
        ->description->toBe('Hot towel included')
        ->tenant_id->toBe($this->tenant->id)
        ->and($service->staff->pluck('id')->all())->toBe([$this->sara->id]);
});

it('validates the service form', function () {
    Livewire::actingAs($this->owner)
        ->test(ServiceIndex::class)
        ->call('create')
        ->set('form.name', '')
        ->set('form.duration_minutes', '3')
        ->set('form.buffer_after_minutes', '-1')
        ->set('form.price', 'free')
        ->call('save')
        ->assertHasErrors(['form.name' => 'required', 'form.duration_minutes', 'form.buffer_after_minutes', 'form.price']);

    expect(Service::count())->toBe(0);
});

it('refuses staff ids from another tenant', function () {
    $foreignStaff = app(TenantContext::class)->runAs(Tenant::factory()->create(), fn () => Staff::factory()->create());

    Livewire::actingAs($this->owner)
        ->test(ServiceIndex::class)
        ->call('create')
        ->set('form.name', 'Sneaky')
        ->set('form.duration_minutes', '30')
        ->set('form.price', '10')
        ->set('form.staffIds', [(string) $foreignStaff->id])
        ->call('save')
        ->assertHasErrors(['form.staffIds.0']);

    expect(Service::count())->toBe(0);
});

it('lets the owner edit and hide a service', function () {
    $service = Service::factory()->create(['name' => 'Haircut', 'active' => true]);
    $service->staff()->attach($this->sara);

    $component = Livewire::actingAs($this->owner)
        ->test(ServiceIndex::class)
        ->call('edit', $service->id)
        ->assertSet('editing', true)
        ->assertSet('form.name', 'Haircut')
        ->assertSet('form.staffIds', [$this->sara->id])
        ->set('form.name', 'Signature haircut')
        ->set('form.staffIds', [])
        ->call('save')
        ->assertHasNoErrors();

    expect($service->refresh())->name->toBe('Signature haircut')
        ->and($service->staff()->count())->toBe(0);

    $component->call('toggleActive', $service->id);

    expect($service->refresh()->active)->toBeFalse();
});

it('forbids members from changing the catalogue', function () {
    $service = Service::factory()->create();

    Livewire::actingAs($this->member)->test(ServiceIndex::class)->call('create')->assertForbidden();
    Livewire::actingAs($this->member)->test(ServiceIndex::class)->call('edit', $service->id)->assertForbidden();
    Livewire::actingAs($this->member)->test(ServiceIndex::class)->call('toggleActive', $service->id)->assertForbidden();

    expect($service->refresh()->active)->toBeTrue();
});
