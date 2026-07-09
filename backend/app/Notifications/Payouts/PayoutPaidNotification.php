<?php

namespace App\Notifications\Payouts;

use App\Models\PayoutRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PayoutPaidNotification extends Notification implements ShouldQueue
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
            ->subject('Payout sent 💸')
            ->greeting("Hi {$notifiable->name},")
            ->line("Your payout of {$this->payout->trader_amount} {$this->payout->currency} has been sent to your bank account.")
            ->line('Keep up the great trading!')
            ->action('View Dashboard', config('app.frontend_url').'/dashboard/wallet');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payout sent 💸',
            'body' => "{$this->payout->trader_amount} {$this->payout->currency} has been sent to your bank account.",
            'action_url' => '/dashboard/wallet',
        ];
    }
}
