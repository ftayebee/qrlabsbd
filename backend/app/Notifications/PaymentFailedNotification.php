<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable, TemplatedMail;

    public function __construct(public readonly Invoice $invoice) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->invoice->total / 100, 2).' '.$this->invoice->currency;

        return $this->templated(
            'payment_failed',
            ['name' => (string) $notifiable->name, 'amount' => $amount],
            'Payment failed for your {{ app_name }} subscription',
            "Hi {{ name }},\n\nWe could not process your payment of {{ amount }}. We will retry automatically over the next few days, and your QR codes keep working in the meantime.\n\nPlease update your payment method to avoid losing premium features.",
            'Update billing',
            rtrim((string) config('app.frontend_url'), '/').'/dashboard/billing',
        );
    }
}
