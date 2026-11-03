<?php

return [
    'admin_origin' => env('STAFF_ADMIN_ORIGIN', 'http://localhost:5173'),
    'idle_seconds' => (int) env('STAFF_IDLE_SECONDS', 1800),
    'absolute_seconds' => (int) env('STAFF_ABSOLUTE_SECONDS', 28800),
    'challenge_seconds' => 300,
    'recovery_days' => 365,
];
