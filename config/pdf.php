<?php

return [
    // "local", "browserless", "screenly", "tailpdf", "puppeteer", "gotenberg", "multi"
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
    'tailpdf' => [
        'token' => env('TAILPDF_TOKEN'),
        'concurrency_limit' => env('TAILPDF_CONCURRENCY_LIMIT', 5), // Maximum number of concurrent requests
    ],
    'gotenberg' => [
        'url' => env('GOTENBERG_URL', 'http://gotenberg:3000'),
        'concurrency_limit' => env('GOTENBERG_CONCURRENCY_LIMIT', 5),
    ],
    'puppeteer' => [
        'concurrency_limit' => env('PUPPETEER_CONCURRENCY_LIMIT', 3), // Maximum number of concurrent processes
    ],
    'multi' => [
        'adapters' => ['screenly', 'gotenberg'], // priority order, first available wins
        'liveness_ttl' => env('PDF_LIVENESS_TTL', 300), // seconds between liveness re-checks
    ],
    'inline_assets' => false,
];
