<?php

use App\Enums\TokenAbility;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

beforeEach(function () {
    $this->tenant = bindTenant(Tenant::factory()->create(['slug' => 'acme']));
    $this->owner = User::factory()->owner()->create(['email' => 'owner@acme.test']);

    $this->other = Tenant::factory()->create(['slug' => 'other']);
});

it('exchanges credentials for a token with read-only abilities by default', function () {
    $response = $this->postJson(apiUrl($this->tenant, '/tokens'), [
        'email' => 'owner@acme.test',
        'password' => 'password',
        'device_name' => 'Front desk iPad',
    ]);

    $response->assertCreated()
        ->assertJsonPath('tenant', 'acme')
        ->assertJsonPath('abilities', ['services:read', 'bookings:read'])
        ->assertJsonStructure(['token', 'abilities', 'tenant']);

    $token = PersonalAccessToken::findToken((string) $response->json('token'));

    expect($token)->not->toBeNull()
        ->and($token->name)->toBe('Front desk iPad')
        ->and($token->tokenable_id)->toBe($this->owner->id)
        ->and($token->can('bookings:write'))->toBeFalse();
});

it('issues only the abilities that were asked for', function () {
    $response = $this->postJson(apiUrl($this->tenant, '/tokens'), [
        'email' => 'owner@acme.test',
        'password' => 'password',
        'device_name' => 'Integration',
        'abilities' => [TokenAbility::WriteBookings->value],
    ])->assertCreated();

    $token = PersonalAccessToken::findToken((string) $response->json('token'));

    expect($token->can('bookings:write'))->toBeTrue()
        ->and($token->can('bookings:read'))->toBeFalse();
});

it('rejects an unknown ability', function () {
    $this->postJson(apiUrl($this->tenant, '/tokens'), [
        'email' => 'owner@acme.test',
        'password' => 'password',
        'device_name' => 'Integration',
        'abilities' => ['tenants:delete'],
    ])->assertUnprocessable()->assertJsonValidationErrors('abilities.0');
});

it('refuses wrong credentials and demands a device name', function () {
    $this->postJson(apiUrl($this->tenant, '/tokens'), [
        'email' => 'owner@acme.test',
        'password' => 'wrong',
        'device_name' => 'Laptop',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson(apiUrl($this->tenant, '/tokens'), [
        'email' => 'owner@acme.test',
        'password' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('device_name');
});

it('will not mint a token for an account that belongs to another tenant', function () {
    $this->postJson(apiUrl($this->other, '/tokens'), [
        'email' => 'owner@acme.test',
        'password' => 'password',
        'device_name' => 'Laptop',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('throttles credential guessing', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson(apiUrl($this->tenant, '/tokens'), [
            'email' => 'owner@acme.test',
            'password' => 'wrong',
            'device_name' => 'Laptop',
        ])->assertUnprocessable();
    }

    $this->postJson(apiUrl($this->tenant, '/tokens'), [
        'email' => 'owner@acme.test',
        'password' => 'password',
        'device_name' => 'Laptop',
    ])->assertStatus(429);
});

it('revokes the token it was called with', function () {
    $plain = $this->owner->createToken('Laptop', TokenAbility::values())->plainTextToken;

    $this->withToken($plain)
        ->deleteJson(apiUrl($this->tenant, '/tokens/current'))
        ->assertOk()
        ->assertJsonPath('revoked', true);

    expect(PersonalAccessToken::findToken($plain))->toBeNull();

    betweenRequests();

    $this->withToken($plain)->getJson(apiUrl($this->tenant, '/bookings'))->assertUnauthorized();
});

it('does not let a token from one tenant act on another', function () {
    $plain = $this->owner->createToken('Laptop', TokenAbility::values())->plainTextToken;

    // The token exists, but its user cannot be resolved inside the other tenant's scope.
    $this->withToken($plain)->getJson(apiUrl($this->other, '/bookings'))->assertUnauthorized();

    betweenRequests();

    $this->withToken($plain)->getJson(apiUrl($this->tenant, '/bookings'))->assertOk();
});

it('refuses an unknown subdomain and requires a token for private endpoints', function () {
    $this->getJson('http://ghost.'.config('tenancy.central_domain').'/api/v1/services')->assertNotFound();
    $this->getJson(centralUrl('/api/v1/services'))->assertNotFound();
    $this->getJson(apiUrl($this->tenant, '/bookings'))->assertUnauthorized();
});
