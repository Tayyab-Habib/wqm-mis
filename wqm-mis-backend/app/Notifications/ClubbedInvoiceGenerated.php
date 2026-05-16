<?php

namespace App\Notifications;

use App\Models\WaterSamples\WaterSampleInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * F-12 / F-16 — Notifies a client when a Clubbed Invoice is generated.
 *
 * Mail uses the standard Laravel mailer. The companion SMS dispatch lives
 * in App\Services\SmsService and is invoked by FinanceInvoiceController.
 */
class ClubbedInvoiceGenerated extends Notification
{
    use Queueable;

    public function __construct(public WaterSampleInvoice $invoice)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $slug     = $this->invoice->clubbed_slug;
        $count    = $this->invoice->childInvoices()->count();
        $total    = number_format((float) $this->invoice->net_amount);
        $password = $this->invoice->online_viewing_password ?? '—';

        return (new MailMessage())
            ->subject("Clubbed Invoice {$slug} generated")
            ->greeting('Dear Client,')
            ->line("A clubbed invoice has been generated for {$count} water-sample receipts.")
            ->line("Invoice No: {$slug}")
            ->line("Total Amount: PKR {$total}")
            ->line("Online Viewing Password: {$password}")
            ->line('You can use the password above to view this invoice online without logging in.')
            ->line('Thank you for using the PHED Water Quality MIS.');
    }
}
