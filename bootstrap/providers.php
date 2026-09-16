<?php

use App\Providers\AppServiceProvider;
use App\Providers\BookingServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    BookingServiceProvider::class,
];
