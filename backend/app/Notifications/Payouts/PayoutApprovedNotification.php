<?php

namespace App\Notifications\Payouts;

use App\Models\PayoutRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PayoutRequest $payout) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your payout has been approved')
            ->greeting("Hi {$notifiable->name},")
            ->line("Your payout request of {$this->payout->trader_amount} {$this->payout->currency} has been approved.")
            ->line('The transfer is being processed and should arrive in your bank account shortly.')
            ->action('View Payouts', config('app.frontend_url').'/dashboard/wallet');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payout approved ✅',
            'body' => "Your payout of {$this->payout->trader_amount} {$this->payout->currency} is being processed.",
            'action_url' => '/dashboard/wallet',
        ];
    }
}
