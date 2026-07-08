<?php

namespace App\Providers;

use App\Services\MT5\HttpMT5BridgeClient;
use App\Services\MT5\MT5BridgeClientInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MT5BridgeClientInterface::class, HttpMT5BridgeClient::class);
    }

    public function boot(): void
    {
        //
    }
}
