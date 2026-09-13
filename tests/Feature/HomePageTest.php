<?php

it('renders the landing page', function () {
    $this->get('/')->assertOk();
});

it('exposes a health check endpoint', function () {
    $this->get('/up')->assertOk();
});
