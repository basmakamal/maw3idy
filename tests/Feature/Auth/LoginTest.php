<?php

use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create(['slug' => 'tenant-a', 'name' => 'Tenant A']);
    $this->tenantB = Tenant::factory()->create(['slug' => 'tenant-b', 'name' => 'Tenant B']);

    $this->userA = User::factory()->for($this->tenantA)->owner()->create(['email' => 'owner@a.test']);
    $this->userB = User::factory()->for($this->tenantB)->owner()->create(['email' => 'owner@b.test']);
});

it('shows the login form for the tenant', function () {
    $this->get(tenantUrl($this->tenantA, '/login'))
        ->assertOk()
        ->assertSee('Sign in to Tenant A')
        ->assertSee('tenant-a.'.config('tenancy.central_domain'));
});

it('authenticates a user on their own tenant subdomain', function () {
    $this->post(tenantUrl($this->tenantA, '/login'), [
        'email' => 'owner@a.test',
        'password' => 'password',
    ])->assertRedirect(tenantUrl($this->tenantA, '/dashboard'));

    $this->assertAuthenticatedAs($this->userA);
});

it('tenant A user cannot authenticate on tenant B subdomain', function () {
    $this->post(tenantUrl($this->tenantB, '/login'), [
        'email' => 'owner@a.test',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('gives the same error for a wrong password, an unknown account and another tenant\'s account', function (string $email, string $password) {
    // One generic message: the form never reveals which accounts exist on this tenant.
    $this->post(tenantUrl($this->tenantA, '/login'), ['email' => $email, 'password' => $password])
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    $this->assertGuest();
})->with([
    'wrong password' => ['owner@a.test', 'nope'],
    'unknown account' => ['ghost@a.test', 'password'],
    'other tenant\'s account' => ['owner@b.test', 'password'],
]);

it('locks an account out after five failed attempts on that tenant only', function () {
    foreach (range(1, 5) as $i) {
        $this->post(tenantUrl($this->tenantA, '/login'), ['email' => 'owner@a.test', 'password' => 'wrong']);
    }

    $this->post(tenantUrl($this->tenantA, '/login'), ['email' => 'owner@a.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');
    $this->assertGuest();

    // Same email and IP, different tenant: not locked out.
    $this->post(tenantUrl($this->tenantB, '/login'), ['email' => 'owner@b.test', 'password' => 'password'])
        ->assertRedirect(tenantUrl($this->tenantB, '/dashboard'));
});

it('does not carry a session from tenant A onto tenant B', function () {
    $this->post(tenantUrl($this->tenantA, '/login'), ['email' => 'owner@a.test', 'password' => 'password']);
    $this->assertAuthenticatedAs($this->userA);

    $this->get(tenantUrl($this->tenantA, '/dashboard'))->assertOk()->assertSee('Tenant A');

    // Each real request re-resolves the user from the session; the test client keeps the
    // guard singleton alive, so reset it to simulate a new request replaying the cookie.
    auth()->forgetGuards();

    // The tenant scope refuses to resolve tenant A's user while tenant B is bound.
    $this->get(tenantUrl($this->tenantB, '/dashboard'))
        ->assertRedirect(tenantUrl($this->tenantB, '/login'));

    auth()->forgetGuards();

    // ...and the original tenant still works with the very same session.
    $this->get(tenantUrl($this->tenantA, '/dashboard'))->assertOk();
});

it('redirects guests to the login page of the same tenant', function () {
    $this->get(tenantUrl($this->tenantB, '/dashboard'))
        ->assertRedirect(tenantUrl($this->tenantB, '/login'));

    $this->get(tenantUrl($this->tenantB, '/'))
        ->assertRedirect(tenantUrl($this->tenantB, '/dashboard'));
});

it('redirects authenticated users away from the login page', function () {
    $this->actingAs($this->userA)
        ->get(tenantUrl($this->tenantA, '/login'))
        ->assertRedirect(tenantUrl($this->tenantA, '/dashboard'));
});

it('logs out and invalidates the session', function () {
    $this->post(tenantUrl($this->tenantA, '/login'), ['email' => 'owner@a.test', 'password' => 'password']);
    $sessionId = session()->getId();

    $this->post(tenantUrl($this->tenantA, '/logout'))
        ->assertRedirect(tenantUrl($this->tenantA, '/login'));

    $this->assertGuest();
    expect(session()->getId())->not->toBe($sessionId);
});

it('regenerates the session id on login', function () {
    $this->get(tenantUrl($this->tenantA, '/login'));
    $before = session()->getId();

    $this->post(tenantUrl($this->tenantA, '/login'), ['email' => 'owner@a.test', 'password' => 'password']);

    expect(session()->getId())->not->toBe($before);
});
