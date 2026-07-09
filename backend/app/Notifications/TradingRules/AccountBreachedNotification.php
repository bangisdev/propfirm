<?php

namespace App\Notifications\TradingRules;

use App\Models\TradingAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountBreachedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly TradingAccount $account,
        private readonly string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Trading account rule violation — account disabled')
            ->greeting("Hi {$notifiable->name},")
            ->line("Your account (Login: {$this->account->mt5_login}) has been disabled.")
            ->line("Reason: {$this->reason}")
            ->line('You can review your account history and start a new challenge from your dashboard.')
            ->action('View Dashboard', config('app.frontend_url').'/dashboard/wallet');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Account disabled ⚠️',
            'body' => $this->reason,
            'action_url' => '/dashboard/wallet',
            'trading_account_id' => $this->account->id,
        ];
    }
}
