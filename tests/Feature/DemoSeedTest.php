<?php

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;

it('seeds two isolated demo tenants a newcomer can sign in to', function () {
    $this->seed();

    expect(Tenant::pluck('slug')->sort()->values()->all())->toBe(['demo', 'jamal'])
        ->and(User::withoutTenancy()->count())->toBe(3);

    $demo = Tenant::where('slug', 'demo')->firstOrFail();
    $jamal = Tenant::where('slug', 'jamal')->firstOrFail();

    $this->post(tenantUrl($demo, '/login'), ['email' => 'owner@demo.test', 'password' => 'password'])
        ->assertRedirect(tenantUrl($demo, '/dashboard'));
    $this->assertAuthenticated();

    $this->post(tenantUrl($demo, '/logout'))->assertRedirect(tenantUrl($demo, '/login'));
    $this->assertGuest();

    // The Arabic owner exists only on the Arabic tenant.
    $this->post(tenantUrl($demo, '/login'), ['email' => 'owner@jamal.test', 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->get(tenantUrl($jamal, '/login'))
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl"', false)
        ->assertSee('صالون الجمال');
});

it('seeds a bookable catalogue for both demo tenants', function () {
    $this->seed();

    $demo = Tenant::where('slug', 'demo')->firstOrFail();
    $jamal = Tenant::where('slug', 'jamal')->firstOrFail();

    // Each tenant sees only its own services on the public page.
    $this->get(tenantUrl($demo, '/book'))->assertOk()->assertSee('Haircut')->assertSee('Hair colour')->assertDontSee('قص شعر');
    $this->get(tenantUrl($jamal, '/book'))->assertOk()->assertSee('قص شعر')->assertSee('صبغة')->assertDontSee('Haircut');

    expect(Booking::withoutTenancy()->confirmed()->count())->toBe(6)
        ->and(Staff::withoutTenancy()->count())->toBe(4)
        ->and(Schedule::withoutTenancy()->count())->toBeGreaterThan(10);
});
