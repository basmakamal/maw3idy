<?php

it('sends defensive headers on every response', function () {
    $this->get(centralUrl())
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
});

it('sends defensive headers on error responses too', function () {
    $this->get(centralUrl('/definitely-not-a-route'))
        ->assertNotFound()
        ->assertHeader('X-Frame-Options', 'DENY');
});

it('enables HSTS only over https', function () {
    $this->get(centralUrl())->assertHeaderMissing('Strict-Transport-Security');

    $this->get(str_replace('http://', 'https://', centralUrl()))
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
