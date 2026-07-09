<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ReplyTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\Support\TicketReplyNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportTicketAdminController extends Controller
{
    /**
     * GET /api/v1/admin/support-tickets?status=open
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('tickets.manage');

        $query = SupportTicket::with('user')->latest('last_reply_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->input('assigned_to'));
        }

        $tickets = $query->paginate(20);

        return response()->json([
            'data' => SupportTicketResource::collection($tickets),
            'meta' => ['current_page' => $tickets->currentPage(), 'total' => $tickets->total()],
        ]);
    }

    /**
     * GET /api/v1/admin/support-tickets/{supportTicket}
     */
    public function show(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $this->authorize('tickets.manage');

        return response()->json([
            'data' => new SupportTicketResource($supportTicket->load(['messages.author', 'user'])),
        ]);
    }

    /**
     * POST /api/v1/admin/support-tickets/{supportTicket}/assign
     */
    public function assign(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $this->authorize('tickets.manage');
        $request->validate(['assigned_to' => ['required', 'uuid', 'exists:users,id']]);

        $agent = User::findOrFail($request->input('assigned_to'));
        $supportTicket->update(['assigned_to' => $agent->id, 'status' => 'in_progress']);

        return response()->json(['data' => new SupportTicketResource($supportTicket->fresh())]);
    }

    /**
     * PATCH /api/v1/admin/support-tickets/{supportTicket}/status
     */
    public function updateStatus(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        $this->authorize('tickets.manage');
        $request->validate(['status' => ['required', 'in:open,in_progress,resolved,closed']]);

        $supportTicket->update(['status' => $request->input('status')]);

        return response()->json(['data' => new SupportTicketResource($supportTicket->fresh())]);
    }

    /**
     * POST /api/v1/admin/support-tickets/{supportTicket}/reply
     */
    public function reply(ReplyTicketRequest $request, SupportTicket $supportTicket): JsonResponse
    {
        $this->authorize('tickets.manage');

        DB::transaction(function () use ($request, $supportTicket) {
            $supportTicket->messages()->create([
                'user_id' => $request->user()->id,
                'message' => $request->input('message'),
                'is_internal_note' => $request->boolean('is_internal_note'),
            ]);

            if (! $request->boolean('is_internal_note')) {
                $supportTicket->update(['status' => 'in_progress', 'last_reply_at' => now()]);
            }
        });

        if (! $request->boolean('is_internal_note')) {
            $supportTicket->user->notify(new TicketReplyNotification($supportTicket));
        }

        return response()->json([
            'data' => new SupportTicketResource($supportTicket->fresh()->load('messages.author')),
        ]);
    }
}
