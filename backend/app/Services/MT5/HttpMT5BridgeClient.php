<?php

namespace App\Services\MT5;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * This talks to a SEPARATE microservice ("mt5-bridge") that wraps your broker's
 * MT5 Manager API — that native API is a Windows-only C/C++/C# SDK, not something
 * a Laravel/PHP process calls directly. The bridge service is out of scope for this
 * repo (it's typically provided or built against your specific broker's SDK); this
 * client is the stable HTTP contract our app calls, so swapping brokers later only
 * means redeploying the bridge, not rewriting this integration.
 */
class HttpMT5BridgeClient implements MT5BridgeClientInterface
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.mt5_bridge.base_url');
        $this->apiKey = (string) config('services.mt5_bridge.api_key');
    }

    public function provisionAccount(array $params): array
    {
        $response = Http::withToken($this->apiKey)
            ->baseUrl($this->baseUrl)
            ->timeout(30)
            ->retry(3, 2000)
            ->post('/accounts', $params);

        if (! $response->successful()) {
            throw new RuntimeException('MT5 bridge failed to provision account: '.$response->body());
        }

        return $response->json();
    }

    public function fetchAccountState(int $login): array
    {
        $response = Http::withToken($this->apiKey)
            ->baseUrl($this->baseUrl)
            ->timeout(15)
            ->get("/accounts/{$login}/state");

        if (! $response->successful()) {
            throw new RuntimeException("MT5 bridge failed to fetch state for account {$login}: ".$response->body());
        }

        return $response->json();
    }

    public function disableAccount(int $login): void
    {
        $response = Http::withToken($this->apiKey)
            ->baseUrl($this->baseUrl)
            ->timeout(15)
            ->post("/accounts/{$login}/disable");

        if (! $response->successful()) {
            throw new RuntimeException("MT5 bridge failed to disable account {$login}: ".$response->body());
        }
    }
}
