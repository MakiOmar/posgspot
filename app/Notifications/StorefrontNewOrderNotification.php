<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * POS bell entry for a new website order (rendered by Util::parseNotifications).
 */
class StorefrontNewOrderNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $summary  StorefrontStaffOrderAlert::summary()
     */
    public function __construct(public array $summary)
    {
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase($notifiable): array
    {
        return [
            'transaction_id' => $this->summary['transaction_id'],
            'invoice_no' => $this->summary['invoice_no'],
            'items_count' => $this->summary['items_count'],
            'total' => $this->summary['total'],
            'currency' => $this->summary['currency'],
            'payment' => $this->summary['payment'],
            'digital_only' => $this->summary['digital_only'],
        ];
    }
}
