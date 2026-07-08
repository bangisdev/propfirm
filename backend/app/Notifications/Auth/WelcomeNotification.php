<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Welcome to PropFirm — Let\'s get you funded')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your account has been created successfully.')
            ->line('Your referral code is: '.$notifiable->referral_code)
            ->action('Browse Challenges', config('app.frontend_url').'/dashboard/challenges')
            ->line('Trade responsibly and good luck with your evaluation!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Welcome to PropFirm 🎉',
            'body' => 'Your account has been created. Start your first challenge today.',
            'action_url' => '/dashboard/challenges',
        ];
    }
}
