<?php

namespace App\Notifications\Payments;

use App\Models\TradingAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountProvisionedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly TradingAccount $account) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your MT5 trading account is ready')
            ->greeting("Hi {$notifiable->name},")
            ->line("Your evaluation account (Login: {$this->account->mt5_login}) is ready to trade.")
            ->line("Server: {$this->account->mt5_server}")
            ->line('Your investor password has been set — log in to your dashboard to view your credentials securely.')
            ->action('View Account', config('app.frontend_url').'/dashboard');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'MT5 account ready 🚀',
            'body' => "Account #{$this->account->mt5_login} is ready to trade.",
            'action_url' => '/dashboard',
        ];
    }
}
