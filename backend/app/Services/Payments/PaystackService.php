<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PaystackService
{
    private string $secretKey;
    private string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = (string) config('services.paystack.secret_key');
    }

    /**
     * Initialize a transaction and return the authorization URL the frontend redirects to.
     * Amount must be passed in the currency's smallest unit (e.g. cents/kobo) per Paystack's API.
     */
    public function initializeTransaction(array $params): array
    {
        $response = Http::withToken($this->secretKey)
            ->baseUrl($this->baseUrl)
            ->post('/transaction/initialize', [
                'email' => $params['email'],
                'amount' => (int) round($params['amount'] * 100),
                'currency' => $params['currency'],
                'reference' => $params['reference'],
                'callback_url' => $params['callback_url'],
                'metadata' => $params['metadata'] ?? [],
            ]);

        if (! $response->successful() || ! $response->json('status')) {
            Log::error('Paystack initialize failed', ['response' => $response->json()]);
            throw new RuntimeException('Unable to initialize payment. Please try again.');
        }

        return $response->json('data');
    }

    /**
     * Server-to-server verification of a transaction — the ONLY source of truth for
     * whether a payment succeeded. Never trust the client-side redirect/callback alone,
     * and never trust the webhook payload without this (or signature) check either.
     */
    public function verifyTransaction(string $reference): array
    {
        $response = Http::withToken($this->secretKey)
            ->baseUrl($this->baseUrl)
            ->get("/transaction/verify/{$reference}");

        if (! $response->successful()) {
            Log::error('Paystack verify failed', ['reference' => $reference, 'response' => $response->json()]);
            throw new RuntimeException('Unable to verify payment status.');
        }

        return $response->json('data');
    }

    /**
     * Validates the `x-paystack-signature` header: HMAC-SHA512 of the raw request
     * body using the secret key. This is what proves a webhook actually came from
     * Paystack and wasn't forged by a third party hitting our public endpoint.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        if (! $signatureHeader) {
            return false;
        }

        $expected = hash_hmac('sha512', $rawBody, $this->secretKey);

        return hash_equals($expected, $signatureHeader);
    }
}
