<?php

declare(strict_types=1);
use App\Models\User;

return [
    'key' => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'path' => env('STRIPE_PATH', '/billing'),
    'webhook' => [
        'secret' => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => 300,
    ],
    'model' => User::class,
];
