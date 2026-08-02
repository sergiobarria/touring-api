<?php

use App\Providers\AppServiceProvider;
use App\Providers\HealthServiceProvider;
use App\Providers\RateLimitServiceProvider;

return [
    AppServiceProvider::class,
    HealthServiceProvider::class,
    RateLimitServiceProvider::class,
];
