<?php

use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = bindTenant(Tenant::factory()->create(['slug' => 'acme']));
});

it('sends a policy with a per-request nonce on HTML pages', function () {
    $first = $this->get(centralUrl('/'))->assertOk();
    $second = $this->get(centralUrl('/'))->assertOk();

    $policy = $first->headers->get('Content-Security-Policy');

    expect($policy)->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->toContain('https://fonts.bunny.net')
        ->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]{24}'/");

    // A nonce that repeated across responses would be no protection at all.
    $nonceOf = fn ($response) => preg_replace("/^.*'nonce-([A-Za-z0-9]+)'.*$/s", '$1', (string) $response->headers->get('Content-Security-Policy'));

    expect($nonceOf($first))->not->toBe($nonceOf($second));
});

it('marks the assets it vouches for with that request\'s nonce', function () {
    $user = User::factory()->owner()->create();

    $response = $this->actingAs($user)->get(tenantUrl($this->tenant, '/dashboard'))->assertOk();

    $nonce = preg_replace("/^.*'nonce-([A-Za-z0-9]+)'.*$/s", '$1', (string) $response->headers->get('Content-Security-Policy'));

    // Livewire's own script and style tags carry it, so the browser runs them.
    expect(substr_count($response->getContent(), 'nonce="'.$nonce.'"'))->toBeGreaterThanOrEqual(2);
});

it('does not put a page policy on API responses', function () {
    $this->getJson(apiUrl($this->tenant, '/services'))
        ->assertOk()
        ->assertHeaderMissing('Content-Security-Policy');
});

it('can be switched to report-only while the policy is being changed', function () {
    config(['security.csp_report_only' => true]);

    $this->get(centralUrl('/'))
        ->assertOk()
        ->assertHeaderMissing('Content-Security-Policy')
        ->assertHeader('Content-Security-Policy-Report-Only');
});

it('still sends the other defensive headers', function () {
    $this->get(centralUrl('/'))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});
