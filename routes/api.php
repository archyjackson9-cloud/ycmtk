<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Reserved for a future JSON/API gateway layer (TOR §5.2 "API Gateway
// (REST or GraphQL) - single entry point for all clients"). The pilot
// storefront and admin panel are server-rendered, so nothing lives here
// yet beyond a liveness check for uptime monitoring (NFR §7 Availability).
Route::get('/health', fn (Request $request) => response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]));
