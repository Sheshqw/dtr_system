<?php

namespace App\Notifications;

use App\Models\DtrPrintConsent;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PrintConsentReviewed extends Notification
{
    use Queueable;

    public function __construct(
        private DtrPrintConsent $consent,
        private string          $status,
        private ?string         $remarks = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved   = $this->status === 'approved';
        $periodLabel = $this->consent->month_label;
        $actionWord  = $approved ? 'Approved' : 'Denied';

        $mail = (new MailMessage)
            ->subject("DTR Print Request {$actionWord} — {$periodLabel}")
            ->greeting("Hello, {$notifiable->first_name}!")
            ->line("Your request to print/download your DTR for **{$periodLabel}** has been **{$actionWord}**.");

        if ($approved) {
            $mail->line('You may now download the official, printable copy of your DTR for this period from the Reports page. This approval is good for a single download.');
        } else {
            $mail->line('You may submit a new request if you still need a printed copy.');
        }

        if ($this->remarks) {
            $mail->line("**Reviewer's Remarks:** {$this->remarks}");
        }

        return $mail
            ->line('---')
            ->line('NEMSU Unified DTR Management System — Human Resources Office');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
