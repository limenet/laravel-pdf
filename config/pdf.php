<?php

return [
    // "local", "browserless", "screenly"
    'strategy' => env('PDF_STRATEGY', 'local'),
    'browserless' => [
        'token' => env('BROWSERLESS_TOKEN'),
        'endpoint' => 'production-ams', // see https://docs.browserless.io/overview/intro#global-endpoints
    ],
    'screenly' => [
        'token' => env('SCREENLY_TOKEN'),
    ],
    'inline_assets' => false,
];
