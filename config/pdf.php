<?php

return [
    // "local", "browserless", "screenly"
    'strategy' => env('PDF_STRATEGY', 'local'),
    'browserless' => [
        'token' => env('BROWSERLESS_TOKEN'),
        'endpoint' => 'production-ams', // see https://docs.browserless.io/overview/intro#global-endpoints
        'concurrency_limit' => env('BROWSERLESS_CONCURRENCY_LIMIT', 5), // Maximum number of concurrent requests
    ],
    'screenly' => [
        'token' => env('SCREENLY_TOKEN'),
        'concurrency_limit' => env('SCREENLY_CONCURRENCY_LIMIT', 5), // Maximum number of concurrent requests
    ],
    'puppeteer' => [
        'concurrency_limit' => env('PUPPETEER_CONCURRENCY_LIMIT', 3), // Maximum number of concurrent processes
    ],
    'inline_assets' => false,
];
