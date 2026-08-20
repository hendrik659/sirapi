<?php

namespace App\Notifications;

use App\Models\IncomingLetter;
use Illuminate\Notifications\Notification;

class IncomingLetterArchivedDirectly extends Notification
{
    public function __construct(public readonly IncomingLetter $incomingLetter) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toDatabase(object $notifiable): array
    {
        $reviewer = $this->incomingLetter->review?->reviewer;
        $sender = $this->incomingLetter->sender_name ?: '-';
        $subject = $this->incomingLetter->subject ?: '-';
        $reviewerName = $reviewer?->name ?? '-';

        return [
            'kind' => 'incoming_letter_archived_directly',
            'title' => 'Surat Masuk Diarsipkan Langsung',
            'message' => "Surat Masuk dari {$sender} dengan perihal {$subject} telah diperiksa dan diarsipkan langsung oleh {$reviewerName} tanpa diteruskan ke divisi.",
            'incoming_letter_id' => $this->incomingLetter->id,
            'reviewer_id' => $reviewer?->id,
            'icon' => 'fa-solid fa-box-archive',
        ];
    }
}
