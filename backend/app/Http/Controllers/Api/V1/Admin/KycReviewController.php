<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\KycSubmissionResource;
use App\Models\KycSubmission;
use App\Services\Kyc\KycService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycReviewController extends Controller
{
    public function __construct(private readonly KycService $kyc) {}

    /**
     * GET /api/v1/admin/kyc-submissions?status=pending
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('kyc.review');

        $query = KycSubmission::with('user')->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $submissions = $query->paginate(20);

        return response()->json([
            'data' => KycSubmissionResource::collection($submissions),
            'meta' => ['current_page' => $submissions->currentPage(), 'total' => $submissions->total()],
        ]);
    }

    /**
     * POST /api/v1/admin/kyc-submissions/{kycSubmission}/approve
     */
    public function approve(Request $request, KycSubmission $kycSubmission): JsonResponse
    {
        $this->authorize('kyc.review');

        $submission = $this->kyc->approve($kycSubmission, $request->user());

        return response()->json(['data' => new KycSubmissionResource($submission)]);
    }

    /**
     * POST /api/v1/admin/kyc-submissions/{kycSubmission}/reject
     */
    public function reject(Request $request, KycSubmission $kycSubmission): JsonResponse
    {
        $this->authorize('kyc.review');
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $submission = $this->kyc->reject($kycSubmission, $request->user(), $request->input('reason'));

        return response()->json(['data' => new KycSubmissionResource($submission)]);
    }
}
