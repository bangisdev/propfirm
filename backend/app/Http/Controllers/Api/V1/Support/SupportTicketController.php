<?php

namespace App\Http\Controllers\Api\V1\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ReplyTicketRequest;
use App\Http\Requests\Support\StoreTicketRequest;
use App\Http\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Notifications\Support\TicketReplyNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportTicketController extends Controller
{
    /**
     * GET /api/v1/support-tickets
     */
    public function index(Request $request): JsonResponse
    {
        $tickets = SupportTicket::where('user_id', $request->user()->id)->latest('last_reply_at')->paginate(20);

        return response()->json([
            'data' => SupportTicketResource::collection($tickets),
            'meta' => ['current_page' => $tickets->currentPage(), 'total' => $tickets->total()],
        ]);
    }

    /**
     * POST /api/v1/support-tickets
     */
    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = DB::transaction(function () use ($request) {
            $ticket = SupportTicket::create([
                'user_id' => $request->user()->id,
                'subject' => $request->input('subject'),
                'category' => $request->input('category'),
                'priority' => $request->input('priority', 'medium'),
                'status' => 'open',
                'last_reply_at' => now(),
            ]);

            $ticket->messages()->create([
                'user_id' => $request->user()->id,
                'message' => $request->input('message'),
                'is_internal_note' => false,
            ]);

            return $ticket;
        });

        return response()->json(['data' => new SupportTicketResource($ticket->load('messages'))], 201);
    }

    /**
     * GET /api/v1/support-tickets/{supportTicket}
     */
    public function show(Request $request, SupportTicket $supportTicket): JsonResponse
    {
        abort_if($supportTicket->user_id !== $request->user()->id, 403);

        return response()->json(['data' => new SupportTicketResource($supportTicket->load(['messages.author', 'user']))]);
    }

    /**
     * POST /api/v1/support-tickets/{supportTicket}/reply
     */
    public function reply(ReplyTicketRequest $request, SupportTicket $supportTicket): JsonResponse
    {
        abort_if($supportTicket->user_id !== $request->user()->id, 403);
        abort_if($supportTicket->isClosed(), 422, 'This ticket is closed. Please open a new one.');

        DB::transaction(function () use ($request, $supportTicket) {
            $supportTicket->messages()->create([
                'user_id' => $request->user()->id,
                'message' => $request->input('message'),
                'is_internal_note' => false,
            ]);

            $supportTicket->update([
                'status' => 'open', // a trader reply reopens an in-progress ticket
                'last_reply_at' => now(),
            ]);
        });

        if ($supportTicket->assigned_to) {
            $supportTicket->assignee->notify(new TicketReplyNotification($supportTicket));
        }

        return response()->json(['data' => new SupportTicketResource($supportTicket->load('messages.author'))]);
    }
}
