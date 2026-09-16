<?php

it('renders the landing page on the central domain', function () {
    $this->get(centralUrl())->assertOk();
});

it('does not serve the landing page from a bare host', function () {
    $this->get('http://localhost/')->assertNotFound();
});

it('exposes a health check endpoint on any host', function () {
    $this->get('http://localhost/up')->assertOk();
    $this->get(centralUrl('/up'))->assertOk();
});
