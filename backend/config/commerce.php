<?php

return [
    'provider' => env('PAYMENT_PROVIDER', 'wise_bank_transfer'),
    'wise' => [
        'enabled' => (bool) env('WISE_ENABLED', false),
        'account_holder' => env('WISE_ACCOUNT_HOLDER', ''),
        'sort_code' => env('WISE_SORT_CODE', ''),
        'account_number' => env('WISE_ACCOUNT_NUMBER', ''),
        'window_hours' => (int) env('WISE_PAYMENT_WINDOW_HOURS', 24),
    ],
    'storefront_origin' => env('STOREFRONT_ORIGIN', 'http://localhost:3000'),
    'enabled' => (bool) env('STRIPE_SANDBOX_ENABLED', false),
    'secret' => env('STRIPE_SECRET_KEY', ''), 'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),
    'test_rates' => true, 'tax_basis_points' => 0,
    'delivery' => ['standard' => ['name' => 'Standard — test rate', 'minor' => 495, 'estimate' => 'Test estimate: 3–5 working days after dispatch'], 'express' => ['name' => 'Express — test rate', 'minor' => 895, 'estimate' => 'Test estimate: 1–2 working days after dispatch']],
    'discount' => ['code' => 'TEST10', 'basis_points' => 1000, 'minimum_minor' => 5000, 'starts_at' => '2026-01-01T00:00:00Z', 'ends_at' => '2030-01-01T00:00:00Z'],
    'session_seconds' => 3600,
];
