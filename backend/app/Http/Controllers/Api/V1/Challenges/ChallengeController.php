<?php

namespace App\Http\Controllers\Api\V1\Challenges;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChallengeResource;
use App\Models\Challenge;
use Illuminate\Http\JsonResponse;

class ChallengeController extends Controller
{
    /**
     * GET /api/v1/challenges — public pricing page data.
     */
    public function index(): JsonResponse
    {
        $challenges = Challenge::active()->get();

        return response()->json(['data' => ChallengeResource::collection($challenges)]);
    }

    /**
     * GET /api/v1/challenges/{challenge}
     */
    public function show(Challenge $challenge): JsonResponse
    {
        if (! $challenge->is_active) {
            abort(404);
        }

        return response()->json(['data' => new ChallengeResource($challenge)]);
    }
}
