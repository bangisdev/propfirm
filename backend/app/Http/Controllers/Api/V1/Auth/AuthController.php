<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use App\Notifications\Auth\WelcomeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    /**
     * POST /api/v1/auth/register
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $referrer = $request->filled('referral_code')
                ? User::where('referral_code', $request->input('referral_code'))->first()
                : null;

            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
                'referral_code' => $this->generateUniqueReferralCode(),
                'referred_by' => $referrer?->id,
            ]);

            $user->assignRole('trader');

            return $user;
        });

        $user->notify(new WelcomeNotification());

        return $this->authResponse($user, $request, 201);
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            $request->hitRateLimiter();

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        if ($user->is_suspended) {
            throw ValidationException::withMessages([
                'email' => 'This account has been suspended. Contact support.',
            ]);
        }

        $request->clearRateLimiter();

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        return $this->authResponse($user, $request, 200);
    }

    /**
     * POST /api/v1/auth/refresh
     * Reads the httpOnly refresh cookie, rotates it, and issues a new access token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $result = $this->refreshTokens->rotate($request);

        if (! $result) {
            return response()->json([
                'message' => 'Session expired. Please log in again.',
            ], 401);
        }

        [$user, $cookie] = $result;

        $accessToken = JWTAuth::fromUser($user);

        return response()->json([
            'data' => [
                'access_token' => $accessToken,
                'expires_in' => config('jwt.ttl') * 60,
            ],
        ])->withCookie($cookie);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => new UserResource($request->user())]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (JWTException) {
            // token already invalid/expired — proceed to clear the refresh cookie anyway
        }

        $cookie = $this->refreshTokens->revoke($request);

        return response()->json(['message' => 'Logged out successfully.'])->withCookie($cookie);
    }

    private function authResponse(User $user, Request $request, int $status): JsonResponse
    {
        $accessToken = JWTAuth::fromUser($user);
        $cookie = $this->refreshTokens->issue($user, $request);

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'access_token' => $accessToken,
                'expires_in' => config('jwt.ttl') * 60,
            ],
        ], $status)->withCookie($cookie);
    }

    private function generateUniqueReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}
