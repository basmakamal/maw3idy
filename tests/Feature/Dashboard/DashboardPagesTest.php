<?php

use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'name' => 'Acme Salon']);
    $this->owner = User::factory()->for($this->tenant)->owner()->create();
    $this->member = User::factory()->for($this->tenant)->create();
});

it('renders each dashboard page for a signed-in user', function (string $path, string $heading) {
    $this->actingAs($this->owner)
        ->get(tenantUrl($this->tenant, $path))
        ->assertOk()
        ->assertSee($heading)
        ->assertSee('Acme Salon')
        ->assertSee('acme.'.config('tenancy.central_domain'));
})->with([
    'dashboard' => ['/dashboard', 'Get set up'],
    'services' => ['/services', 'No services yet'],
    'staff' => ['/staff', 'No staff yet'],
    'calendar' => ['/calendar', 'Nothing scheduled'],
]);

it('sends guests to the tenant login page', function (string $path) {
    $this->get(tenantUrl($this->tenant, $path))
        ->assertRedirect(tenantUrl($this->tenant, '/login'));
})->with(['/dashboard', '/services', '/staff', '/calendar', '/settings']);

it('does not serve dashboard pages from the central domain', function (string $path) {
    $this->actingAs($this->owner)->get(centralUrl($path))->assertNotFound();
})->with(['/dashboard', '/services', '/settings', '/login']);

it('shows the settings link to owners only', function () {
    $this->actingAs($this->owner)
        ->get(tenantUrl($this->tenant, '/dashboard'))
        ->assertSee(tenantUrl($this->tenant, '/settings'));

    $this->actingAs($this->member)
        ->get(tenantUrl($this->tenant, '/dashboard'))
        ->assertDontSee(tenantUrl($this->tenant, '/settings'));
});

it('renders Arabic tenants right-to-left', function () {
    $arabic = Tenant::factory()->arabic()->create(['slug' => 'salon-ar']);
    $owner = User::factory()->for($arabic)->owner()->create();

    $this->actingAs($owner)
        ->get(tenantUrl($arabic, '/dashboard'))
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl"', false);

    $this->actingAs($this->owner)
        ->get(tenantUrl($this->tenant, '/dashboard'))
        ->assertSee('<html lang="en" dir="ltr"', false);
});
