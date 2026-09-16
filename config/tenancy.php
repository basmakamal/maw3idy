<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Central domain
    |--------------------------------------------------------------------------
    |
    | Serves the landing page and tenant registration only. Every tenant is
    | reached at {slug}.central_domain. Browsers resolve *.localhost to the
    | loopback address without a hosts entry, which is why it is the default.
    |
    */

    'central_domain' => env('APP_CENTRAL_DOMAIN', 'maw3idy.localhost'),

    /*
    |--------------------------------------------------------------------------
    | Reserved subdomains
    |--------------------------------------------------------------------------
    |
    | Labels a tenant may never claim, either because infrastructure needs them
    | or because they would let a tenant impersonate the platform.
    |
    */

    'reserved_subdomains' => [
        'www', 'app', 'api', 'admin', 'administrator', 'root', 'system',
        'mail', 'smtp', 'imap', 'pop', 'mx', 'ftp', 'ns', 'ns1', 'ns2',
        'static', 'assets', 'cdn', 'media', 'files', 'img',
        'status', 'help', 'support', 'docs', 'blog', 'news',
        'dev', 'staging', 'test', 'demo-admin', 'billing', 'pay', 'login', 'auth',
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales & timezone defaults
    |--------------------------------------------------------------------------
    */

    'supported_locales' => ['en', 'ar'],

    'default_timezone' => 'Asia/Riyadh',

];
