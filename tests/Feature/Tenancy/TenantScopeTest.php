<?php

use App\Exceptions\Tenancy\TenantMismatchException;
use App\Exceptions\Tenancy\TenantNotBoundException;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
    $this->tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);

    $this->userA = User::factory()->for($this->tenantA)->create(['email' => 'a@example.com']);
    $this->userB = User::factory()->for($this->tenantB)->create(['email' => 'b@example.com']);
});

it('tenant A cannot see tenant B data', function () {
    bindTenant($this->tenantA);

    expect(User::all()->pluck('email')->all())->toBe(['a@example.com'])
        ->and(User::find($this->userB->id))->toBeNull()
        ->and(User::where('email', 'b@example.com')->exists())->toBeFalse();

    bindTenant($this->tenantB);

    expect(User::all()->pluck('email')->all())->toBe(['b@example.com']);
});

it('scopes new records to the current tenant automatically', function () {
    bindTenant($this->tenantA);

    $user = User::create([
        'name' => 'New Hire',
        'email' => 'new@example.com',
        'password' => 'secret-password',
    ]);

    expect($user->tenant_id)->toBe($this->tenantA->id)
        ->and($user->tenant->is($this->tenantA))->toBeTrue();
});

it('refuses to create a record for another tenant while one is bound', function () {
    bindTenant($this->tenantA);

    $this->tenantB->users()->create([
        'name' => 'Intruder',
        'email' => 'intruder@example.com',
        'password' => 'secret-password',
    ]);
})->throws(TenantMismatchException::class);

it('refuses to move a record between tenants', function () {
    bindTenant($this->tenantA);

    $this->userA->tenant_id = $this->tenantB->id;
    $this->userA->save();
})->throws(TenantMismatchException::class);

it('fails closed when no tenant is bound', function () {
    expect(app(TenantContext::class)->has())->toBeFalse();

    User::all();
})->throws(TenantNotBoundException::class);

it('refuses to create a record with no tenant bound and no explicit tenant', function () {
    User::create([
        'name' => 'Nobody',
        'email' => 'nobody@example.com',
        'password' => 'secret-password',
    ]);
})->throws(TenantNotBoundException::class);

it('allows an explicit cross-tenant query via withoutTenancy', function () {
    expect(User::withoutTenancy()->count())->toBe(2);

    bindTenant($this->tenantA);

    expect(User::count())->toBe(1)
        ->and(User::withoutTenancy()->count())->toBe(2);
});

it('runAs binds a tenant for the callback and restores the previous one', function () {
    bindTenant($this->tenantA);

    $emails = app(TenantContext::class)->runAs($this->tenantB, fn () => User::pluck('email')->all());

    expect($emails)->toBe(['b@example.com'])
        ->and(app(TenantContext::class)->current()->is($this->tenantA))->toBeTrue();
});

it('runAs restores the previous tenant even when the callback throws', function () {
    bindTenant($this->tenantA);

    try {
        app(TenantContext::class)->runAs($this->tenantB, fn () => throw new RuntimeException('boom'));
    } catch (RuntimeException) {
        // expected
    }

    expect(app(TenantContext::class)->current()->is($this->tenantA))->toBeTrue();
});

it('can create records for a specific tenant from the central context via runAs', function () {
    $user = app(TenantContext::class)->runAs($this->tenantB, fn () => User::create([
        'name' => 'Created Centrally',
        'email' => 'central@example.com',
        'password' => 'secret-password',
    ]));

    expect($user->tenant_id)->toBe($this->tenantB->id)
        ->and(app(TenantContext::class)->has())->toBeFalse();
});
