<?php

namespace App\Notifications\Support;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketReplyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly SupportTicket $ticket) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("New reply on your ticket: {$this->ticket->subject}")
            ->greeting("Hi {$notifiable->name},")
            ->line("There's a new reply on your support ticket \"{$this->ticket->subject}\".")
            ->action('View Ticket', config('app.frontend_url').'/dashboard/support/'.$this->ticket->id);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New support ticket reply',
            'body' => $this->ticket->subject,
            'action_url' => '/dashboard/support/'.$this->ticket->id,
        ];
    }
}
