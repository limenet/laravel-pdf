<?php

return [
    // "local", "browserless", "screenly"
    'strategy' => env('PDF_STRATEGY', 'local'),
    'browserless' => [
        'token' => env('BROWSERLESS_TOKEN'),
    ],
    'screenly' => [
        'token' => env('SCREENLY_TOKEN'),
    ],
    'inline_assets' => false,
];
