<?php

use App\Livewire\Settings\GeneralSettings;
use App\Models\Tenant;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'name' => 'Acme Salon', 'timezone' => 'Asia/Riyadh', 'locale' => 'en']);
    $this->owner = User::factory()->for($this->tenant)->owner()->create();
    $this->member = User::factory()->for($this->tenant)->create();
});

it('shows the settings page to the owner with current values', function () {
    $this->actingAs($this->owner)
        ->get(tenantUrl($this->tenant, '/settings'))
        ->assertOk()
        ->assertSeeLivewire(GeneralSettings::class)
        ->assertSee('Asia/Riyadh');
});

it('forbids members from the settings page', function () {
    $this->actingAs($this->member)
        ->get(tenantUrl($this->tenant, '/settings'))
        ->assertForbidden();
});

it('lets the owner update name, timezone and language', function () {
    bindTenant($this->tenant);

    Livewire::actingAs($this->owner)
        ->test(GeneralSettings::class)
        ->assertSet('name', 'Acme Salon')
        ->set('name', 'Acme Salon & Spa')
        ->set('timezone', 'Europe/London')
        ->set('locale', 'ar')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(tenantUrl($this->tenant, '/settings'));

    expect($this->tenant->refresh())
        ->name->toBe('Acme Salon & Spa')
        ->timezone->toBe('Europe/London')
        ->locale->toBe('ar');
});

it('validates the settings', function () {
    bindTenant($this->tenant);

    Livewire::actingAs($this->owner)
        ->test(GeneralSettings::class)
        ->set('name', '')
        ->set('timezone', 'Mars/Olympus')
        ->set('locale', 'fr')
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'timezone', 'locale']);

    expect($this->tenant->refresh()->timezone)->toBe('Asia/Riyadh');
});

it('forbids members from mounting the component at all', function () {
    bindTenant($this->tenant);

    Livewire::actingAs($this->member)
        ->test(GeneralSettings::class)
        ->assertForbidden();
});

it('never lets an owner of another tenant touch these settings', function () {
    $other = Tenant::factory()->create();
    $otherOwner = User::factory()->for($other)->owner()->create();

    bindTenant($this->tenant);

    Livewire::actingAs($otherOwner)
        ->test(GeneralSettings::class)
        ->assertForbidden();
});
