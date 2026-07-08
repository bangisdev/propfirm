<?php

use Illuminate\Support\Facades\Route;

// This is an API-only backend — the SPA is served separately by the frontend
// container/nginx. This route just confirms the API is up when hit directly.
Route::get('/', function () {
    return response()->json([
        'name' => config('app.name'),
        'status' => 'ok',
        'docs' => '/docs/openapi.yaml',
    ]);
});
