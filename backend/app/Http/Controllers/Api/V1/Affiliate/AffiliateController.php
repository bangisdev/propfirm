<?php

namespace App\Http\Controllers\Api\V1\Affiliate;

use App\Http\Controllers\Controller;
use App\Http\Resources\AffiliateCommissionResource;
use App\Models\AffiliateCommission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    /**
     * GET /api/v1/affiliate/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $totalReferrals = User::where('referred_by', $user->id)->count();
        $paidReferrals = User::where('referred_by', $user->id)
            ->whereHas('orders', fn ($q) => $q->where('status', 'paid'))
            ->count();

        $commissions = AffiliateCommission::where('affiliate_user_id', $user->id);

        return response()->json(['data' => [
            'referral_code' => $user->referral_code,
            'total_referrals' => $totalReferrals,
            'converted_referrals' => $paidReferrals,
            'total_earned' => (float) (clone $commissions)->where('status', 'paid')->sum('commission_amount'),
            'total_pending' => (float) (clone $commissions)->where('status', 'pending')->sum('commission_amount'),
            'total_processing' => (float) (clone $commissions)->where('status', 'processing')->sum('commission_amount'),
        ]]);
    }

    /**
     * GET /api/v1/affiliate/referrals
     */
    public function referrals(Request $request): JsonResponse
    {
        $referrals = User::where('referred_by', $request->user()->id)
            ->withCount(['orders as paid_orders_count' => fn ($q) => $q->where('status', 'paid')])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $referrals->map(fn ($u) => [
                'name' => $u->name,
                'joined_at' => $u->created_at->toIso8601String(),
                'has_converted' => $u->paid_orders_count > 0,
            ]),
            'meta' => ['current_page' => $referrals->currentPage(), 'total' => $referrals->total()],
        ]);
    }

    /**
     * GET /api/v1/affiliate/commissions
     */
    public function commissions(Request $request): JsonResponse
    {
        $commissions = AffiliateCommission::with('referredUser')
            ->where('affiliate_user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => AffiliateCommissionResource::collection($commissions),
            'meta' => ['current_page' => $commissions->currentPage(), 'total' => $commissions->total()],
        ]);
    }
}
