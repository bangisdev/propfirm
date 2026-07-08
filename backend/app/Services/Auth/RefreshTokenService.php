<?php

namespace App\Services\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as CookieDto;

class RefreshTokenService
{
    private const COOKIE_NAME = 'refresh_token';
    private const TTL_DAYS = 30;

    /**
     * Issue a brand-new refresh token for the user, store only its hash,
     * and return a Set-Cookie response cookie carrying the raw value.
     */
    public function issue(User $user, Request $request): CookieDto
    {
        $raw = Str::random(64);

        RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $raw),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'ip_address' => $request->ip(),
            'expires_at' => now()->addDays(self::TTL_DAYS),
        ]);

        return $this->makeCookie($raw);
    }

    /**
     * Validate the raw refresh token from the incoming cookie, rotate it
     * (revoke old, issue new) and return [User, CookieDto] or null if invalid.
     *
     * Rotation prevents replay: if a stolen token is used after the legitimate
     * client already rotated it, the stolen one will already be revoked.
     */
    public function rotate(Request $request): ?array
    {
        $raw = $request->cookie(self::COOKIE_NAME);
        if (! $raw) {
            return null;
        }

        $hash = hash('sha256', $raw);
        $token = RefreshToken::where('token_hash', $hash)->first();

        if (! $token || ! $token->isValid()) {
            return null;
        }

        $token->update(['revoked_at' => now()]);

        $user = $token->user;
        if (! $user || $user->is_suspended) {
            return null;
        }

        $newCookie = $this->issue($user, $request);

        return [$user, $newCookie];
    }

    public function revoke(Request $request): CookieDto
    {
        $raw = $request->cookie(self::COOKIE_NAME);
        if ($raw) {
            RefreshToken::where('token_hash', hash('sha256', $raw))
                ->update(['revoked_at' => now()]);
        }

        return $this->forgetCookie();
    }

    public function revokeAllForUser(User $user): void
    {
        RefreshToken::where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function makeCookie(string $raw): CookieDto
    {
        return Cookie::make(
            name: self::COOKIE_NAME,
            value: $raw,
            minutes: self::TTL_DAYS * 24 * 60,
            path: '/api/v1/auth',
            domain: null,
            secure: (bool) config('session.secure_cookie', true),
            httpOnly: true,
            raw: false,
            sameSite: 'strict',
        );
    }

    private function forgetCookie(): CookieDto
    {
        return Cookie::forget(self::COOKIE_NAME, '/api/v1/auth');
    }
}
