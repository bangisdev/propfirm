<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Internal staff notes are filtered out for traders — they never see
        // messages meant for handoff context between support agents.
        $isStaff = $request->user()?->can('tickets.manage');

        $messages = $this->whenLoaded('messages', fn () => $this->messages
            ->filter(fn ($m) => $isStaff || ! $m->is_internal_note)
            ->values());

        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'category' => $this->category,
            'priority' => $this->priority,
            'status' => $this->status,
            'trader' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
            ] : null),
            'messages' => $messages !== null ? SupportTicketMessageResource::collection($messages) : null,
            'last_reply_at' => $this->last_reply_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
