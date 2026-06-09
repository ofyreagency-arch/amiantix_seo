<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    \Ofyre\SeoEngine\SeoEngineServiceProvider::class,
    \App\Providers\SeoRuntimeServiceProvider::class,
];
