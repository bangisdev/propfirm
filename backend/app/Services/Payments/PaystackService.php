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
     * Verifies a bank account exists and returns the account holder's name — used
     * to confirm the trader's bank details before creating a transfer recipient,
     * so a typo'd account number is caught before we ever attempt to pay it.
     */
    public function resolveAccountNumber(string $accountNumber, string $bankCode): array
    {
        $response = Http::withToken($this->secretKey)
            ->baseUrl($this->baseUrl)
            ->get('/bank/resolve', ['account_number' => $accountNumber, 'bank_code' => $bankCode]);

        if (! $response->successful() || ! $response->json('status')) {
            Log::warning('Paystack account resolution failed', ['response' => $response->json()]);
            throw new RuntimeException('Could not verify this bank account. Please check the details and try again.');
        }

        return $response->json('data');
    }

    /**
     * Creates a Paystack transfer recipient (a reusable payout destination) —
     * done once per bank account, then the returned recipient_code is stored
     * and reused for every subsequent transfer to that account.
     */
    public function createTransferRecipient(array $params): array
    {
        $response = Http::withToken($this->secretKey)
            ->baseUrl($this->baseUrl)
            ->post('/transferrecipient', [
                'type' => 'nuban',
                'name' => $params['account_name'],
                'account_number' => $params['account_number'],
                'bank_code' => $params['bank_code'],
                'currency' => $params['currency'] ?? 'NGN',
            ]);

        if (! $response->successful() || ! $response->json('status')) {
            Log::error('Paystack recipient creation failed', ['response' => $response->json()]);
            throw new RuntimeException('Unable to set up payout destination.');
        }

        return $response->json('data');
    }

    /**
     * Initiates the actual transfer of funds to a recipient. Amount in the
     * currency's smallest unit, matching initializeTransaction's convention.
     */
    public function initiateTransfer(array $params): array
    {
        $response = Http::withToken($this->secretKey)
            ->baseUrl($this->baseUrl)
            ->post('/transfer', [
                'source' => 'balance',
                'amount' => (int) round($params['amount'] * 100),
                'recipient' => $params['recipient_code'],
                'reason' => $params['reason'] ?? 'Trader payout',
                'reference' => $params['reference'],
            ]);

        if (! $response->successful() || ! $response->json('status')) {
            Log::error('Paystack transfer failed', ['response' => $response->json()]);
            throw new RuntimeException('Transfer could not be initiated.');
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
