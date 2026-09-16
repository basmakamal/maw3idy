<?php

use App\Livewire\Staff\StaffIndex;
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
    $this->haircut = Service::factory()->create(['name' => 'Haircut']);
});

it('renders the staff page for any user with links to each schedule', function () {
    $sara = Staff::factory()->create(['name' => 'Sara']);

    $this->actingAs($this->member)
        ->get(tenantUrl($this->tenant, '/staff'))
        ->assertOk()
        ->assertSeeLivewire(StaffIndex::class)
        ->assertSee('Sara')
        ->assertSee(tenantUrl($this->tenant, "/staff/{$sara->id}/schedule"))
        ->assertDontSee('Add staff member');
});

it('lets the owner add a staff member who offers chosen services', function () {
    Livewire::actingAs($this->owner)
        ->test(StaffIndex::class)
        ->call('create')
        ->set('form.name', 'Omar')
        ->set('form.email', 'omar@example.com')
        ->set('form.serviceIds', [(string) $this->haircut->id])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Omar');

    $omar = Staff::query()->where('name', 'Omar')->sole();

    expect($omar)->email->toBe('omar@example.com')->active->toBeTrue()->tenant_id->toBe($this->tenant->id)
        ->and($omar->services->pluck('id')->all())->toBe([$this->haircut->id]);
});

it('validates and refuses services from another tenant', function () {
    $foreignService = app(TenantContext::class)->runAs(Tenant::factory()->create(), fn () => Service::factory()->create());

    Livewire::actingAs($this->owner)
        ->test(StaffIndex::class)
        ->call('create')
        ->set('form.name', '')
        ->set('form.email', 'not-an-email')
        ->set('form.serviceIds', [(string) $foreignService->id])
        ->call('save')
        ->assertHasErrors(['form.name' => 'required', 'form.email', 'form.serviceIds.0']);

    expect(Staff::count())->toBe(0);
});

it('lets the owner edit and deactivate a staff member', function () {
    $sara = Staff::factory()->create(['name' => 'Sara']);
    $sara->services()->attach($this->haircut);

    $component = Livewire::actingAs($this->owner)
        ->test(StaffIndex::class)
        ->call('edit', $sara->id)
        ->assertSet('form.name', 'Sara')
        ->assertSet('form.serviceIds', [$this->haircut->id])
        ->set('form.name', 'Sara A.')
        ->set('form.email', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($sara->refresh())->name->toBe('Sara A.')->email->toBeNull();

    $component->call('toggleActive', $sara->id);

    expect($sara->refresh()->active)->toBeFalse();
});

it('forbids members from changing staff', function () {
    $sara = Staff::factory()->create();

    Livewire::actingAs($this->member)->test(StaffIndex::class)->call('create')->assertForbidden();
    Livewire::actingAs($this->member)->test(StaffIndex::class)->call('edit', $sara->id)->assertForbidden();
    Livewire::actingAs($this->member)->test(StaffIndex::class)->call('toggleActive', $sara->id)->assertForbidden();
});
