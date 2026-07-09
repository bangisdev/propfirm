<?php

namespace App\Notifications\Payouts;

use App\Models\PayoutRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly PayoutRequest $payout,
        private readonly string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your payout request needs attention')
            ->greeting("Hi {$notifiable->name},")
            ->line("Your payout request of {$this->payout->requested_amount} {$this->payout->currency} was not approved.")
            ->line("Reason: {$this->reason}")
            ->line('Please contact support if you have questions, or submit a new request once resolved.')
            ->action('Contact Support', config('app.frontend_url').'/dashboard/support');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payout request declined',
            'body' => $this->reason,
            'action_url' => '/dashboard/wallet',
        ];
    }
}
