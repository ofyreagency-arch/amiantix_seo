<?php

declare(strict_types=1);

return [
    'praeviseo_url' => env('PRAEVISEO_URL', 'https://app.praeviseo.com'),
    'site_id' => env('PRAEVISEO_BRIDGE_SITE_ID'),
    'secret' => env('PRAEVISEO_BRIDGE_SECRET'),
    'prefix' => env('PRAEVISEO_BRIDGE_PREFIX', 'ressources'),
];
