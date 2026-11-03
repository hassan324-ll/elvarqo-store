<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['service' => 'elvarqo-api', 'health' => '/api/v1/health']));
