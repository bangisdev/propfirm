<?php

namespace App\Notifications\Kyc;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KycApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Identity verification approved')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your identity verification has been approved.')
            ->line('You now have full access to withdrawals and payouts.')
            ->action('View Dashboard', config('app.frontend_url').'/dashboard');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'KYC approved',
            'body' => 'Your identity has been verified.',
            'action_url' => '/dashboard/settings',
        ];
    }
}
