<?php

namespace App\Notifications\Payments;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPaidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Payment received — your trading account is being set up')
            ->greeting("Hi {$notifiable->name},")
            ->line("We've received your payment of {$this->order->total} {$this->order->currency}.")
            ->line('Your MT5 trading account is being provisioned and will appear in your dashboard shortly.')
            ->action('View Dashboard', config('app.frontend_url').'/dashboard');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Payment confirmed 🎉',
            'body' => "Your challenge account is being provisioned.",
            'action_url' => '/dashboard/wallet',
            'order_id' => $this->order->id,
        ];
    }
}
