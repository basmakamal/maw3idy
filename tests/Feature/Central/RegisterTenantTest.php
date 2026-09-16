<?php

use App\Actions\Tenancy\RegisterTenant;
use App\Data\TenantRegistrationData;
use App\Enums\UserRole;
use App\Events\TenantRegistered;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

/**
 * @return array<string, string>
 */
function validRegistration(array $overrides = []): array
{
    return array_merge([
        'business_name' => 'Acme Salon',
        'slug' => 'acme',
        'timezone' => 'Asia/Riyadh',
        'locale' => 'en',
        'name' => 'Basma Owner',
        'email' => 'owner@acme.test',
        'password' => 'correct-horse-battery',
        'password_confirmation' => 'correct-horse-battery',
    ], $overrides);
}

it('shows the registration form on the central domain', function () {
    $this->get(centralUrl('/register'))
        ->assertOk()
        ->assertSee('Create your booking page')
        ->assertSee('.'.config('tenancy.central_domain'));
});

it('is not reachable from a tenant subdomain', function () {
    $tenant = Tenant::factory()->create();

    $this->get(tenantUrl($tenant, '/register'))->assertNotFound();
    $this->post(tenantUrl($tenant, '/register'), validRegistration())->assertNotFound();
});

it('creates the tenant and its owner and sends them to their own login page', function () {
    Event::fake([TenantRegistered::class]);

    $response = $this->post(centralUrl('/register'), validRegistration());

    $tenant = Tenant::where('slug', 'acme')->firstOrFail();

    expect($tenant)
        ->name->toBe('Acme Salon')
        ->timezone->toBe('Asia/Riyadh')
        ->locale->toBe('en');

    $owner = User::withoutTenancy()->where('email', 'owner@acme.test')->firstOrFail();

    expect($owner->tenant_id)->toBe($tenant->id)
        ->and($owner->role)->toBe(UserRole::Owner)
        ->and($owner->name)->toBe('Basma Owner')
        ->and(Hash::check('correct-horse-battery', $owner->password))->toBeTrue();

    $response->assertRedirect(tenantUrl($tenant, '/login?registered=1'));

    Event::assertDispatched(TenantRegistered::class, fn (TenantRegistered $event) => $event->tenant->is($tenant) && $event->owner->is($owner));
});

it('normalises the address to lowercase and treats it as case-insensitive', function () {
    Tenant::factory()->create(['slug' => 'acme']);

    $this->post(centralUrl('/register'), validRegistration(['slug' => 'ACME']))
        ->assertSessionHasErrors(['slug' => 'This address is already taken.']);

    $this->post(centralUrl('/register'), validRegistration(['slug' => 'Fresh-Cuts']))
        ->assertSessionDoesntHaveErrors();

    expect(Tenant::where('slug', 'fresh-cuts')->exists())->toBeTrue();
});

it('rejects reserved addresses', function (string $slug) {
    $this->post(centralUrl('/register'), validRegistration(['slug' => $slug]))
        ->assertSessionHasErrors(['slug' => 'This address is reserved. Please choose another.']);

    expect(Tenant::count())->toBe(0);
})->with(['www', 'api', 'admin', 'mail', 'login']);

it('rejects malformed addresses', function (string $slug) {
    $this->post(centralUrl('/register'), validRegistration(['slug' => $slug]))
        ->assertSessionHasErrors('slug');

    expect(Tenant::count())->toBe(0);
})->with([
    'too short' => 'ab',
    'leading hyphen' => '-acme',
    'trailing hyphen' => 'acme-',
    'dot' => 'acme.shop',
    'space' => 'acme shop',
    'underscore' => 'acme_shop',
    'unicode' => 'صالون',
    'too long' => str_repeat('a', 64),
]);

it('rejects an unknown timezone or unsupported locale', function () {
    $this->post(centralUrl('/register'), validRegistration(['timezone' => 'Mars/Olympus']))
        ->assertSessionHasErrors('timezone');

    $this->post(centralUrl('/register'), validRegistration(['locale' => 'fr']))
        ->assertSessionHasErrors('locale');

    expect(Tenant::count())->toBe(0);
});

it('enforces the application password policy', function () {
    $this->post(centralUrl('/register'), validRegistration([
        'password' => 'short',
        'password_confirmation' => 'short',
    ]))->assertSessionHasErrors('password');

    $this->post(centralUrl('/register'), validRegistration([
        'password_confirmation' => 'does-not-match',
    ]))->assertSessionHasErrors('password');

    expect(Tenant::count())->toBe(0);
});

it('rolls the tenant back when the owner cannot be created', function () {
    User::creating(fn () => throw new RuntimeException('owner insert failed'));

    $action = app(RegisterTenant::class);
    $data = new TenantRegistrationData(
        businessName: 'Acme Salon',
        slug: 'acme',
        timezone: 'Asia/Riyadh',
        locale: 'en',
        ownerName: 'Basma Owner',
        ownerEmail: 'owner@acme.test',
        ownerPassword: 'correct-horse-battery',
    );

    expect(fn () => $action->handle($data))->toThrow(RuntimeException::class);

    expect(Tenant::count())->toBe(0)
        ->and(User::withoutTenancy()->count())->toBe(0)
        ->and(app(TenantContext::class)->has())->toBeFalse();
});

it('rate limits registration attempts per IP', function () {
    foreach (range(1, 5) as $i) {
        $this->post(centralUrl('/register'), validRegistration(['slug' => "salon-{$i}", 'email' => "owner{$i}@acme.test"]))
            ->assertRedirect();
    }

    $this->post(centralUrl('/register'), validRegistration(['slug' => 'salon-6', 'email' => 'owner6@acme.test']))
        ->assertTooManyRequests();

    expect(Tenant::count())->toBe(5);
});
