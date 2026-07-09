<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SupportTicketMessage extends Model
{
    use HasUuids;

    protected $fillable = ['ticket_id', 'user_id', 'message', 'is_internal_note'];

    protected function casts(): array
    {
        return ['is_internal_note' => 'boolean'];
    }

    public function ticket()
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
