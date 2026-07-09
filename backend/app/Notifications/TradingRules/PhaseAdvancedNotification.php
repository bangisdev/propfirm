<?php

namespace App\Notifications\TradingRules;

use App\Models\TradingAccount;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PhaseAdvancedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly TradingAccount $account,
        private readonly string $newPhase,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage())->greeting("Hi {$notifiable->name},");

        if ($this->newPhase === 'funded') {
            return $message
                ->subject('Congratulations — you are now a funded trader! 🎉')
                ->line("Your account (Login: {$this->account->mt5_login}) has passed evaluation and is now funded.")
                ->line('You can now request payouts according to your profit split once eligible.')
                ->action('View Dashboard', config('app.frontend_url').'/dashboard/wallet');
        }

        return $message
            ->subject('Phase 1 passed — on to Phase 2!')
            ->line("Your account (Login: {$this->account->mt5_login}) has passed Phase 1 of the evaluation.")
            ->line('Your trading day count has reset — Phase 2 has its own profit target and minimum days.')
            ->action('View Dashboard', config('app.frontend_url').'/dashboard/wallet');
    }

    public function toArray(object $notifiable): array
    {
        $body = $this->newPhase === 'funded'
            ? 'You passed your evaluation and are now a funded trader!'
            : 'You passed Phase 1 — Phase 2 has begun.';

        return [
            'title' => $this->newPhase === 'funded' ? 'Funded! 🎉' : 'Phase 1 passed 🚀',
            'body' => $body,
            'action_url' => '/dashboard/wallet',
            'trading_account_id' => $this->account->id,
        ];
    }
}
