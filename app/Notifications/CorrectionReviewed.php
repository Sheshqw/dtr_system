<?php

namespace App\Notifications;

use App\Models\DtrCorrection;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CorrectionReviewed extends Notification
{
    use Queueable;

    public function __construct(
        private DtrCorrection $correction,
        private string        $status,
        private ?string       $remarks = null,
        private int           $fieldCount = 1,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved   = $this->status === 'approved';
        $dateLabel  = $this->correction->work_date?->format('F j, Y');
        $fieldsText = $this->fieldCount > 1
            ? "{$this->fieldCount} time fields"
            : $this->correction->field_label;
        $actionWord = $approved ? 'Approved' : 'Denied';

        $mail = (new MailMessage)
            ->subject("DTR Correction {$actionWord} — {$dateLabel}")
            ->greeting("Hello, {$notifiable->first_name}!")
            ->line("Your DTR correction request for **{$fieldsText}** on **{$dateLabel}** has been **{$actionWord}**.");

        if ($approved) {
            $mail->line('Your Daily Time Record has been updated accordingly.');
        }

        if ($this->remarks) {
            $mail->line("**Reviewer's Remarks:** {$this->remarks}");
        }

        if (! $approved) {
            $mail->line('You may submit a new correction request with updated information if needed.');
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
