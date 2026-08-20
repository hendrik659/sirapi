<?php

namespace App\Notifications;

use App\Models\IncomingLetter;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IncomingLetterForwardedToDivision extends Notification
{
    public function __construct(public readonly IncomingLetter $incomingLetter) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $divisionName = $this->incomingLetter->destinationDivision?->name ?? '-';

        return (new MailMessage)
            ->subject('[SIRAPI] Surat Masuk untuk Divisi Anda')
            ->greeting("Halo, {$notifiable->name}.")
            ->line('Surat Masuk telah selesai diperiksa dan diteruskan ke divisi Anda.')
            ->line("Divisi tujuan: {$divisionName}")
            ->line('Pengirim: '.($this->incomingLetter->sender_name ?: '-'))
            ->line('Perihal: '.($this->incomingLetter->subject ?: '-'))
            ->action('Lihat Surat di SIRAPI', route('incoming-letters.show', $this->incomingLetter))
            ->line('Silakan masuk ke SIRAPI untuk melihat detail dan dokumen surat.');
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toDatabase(object $notifiable): array
    {
        $divisionName = $this->incomingLetter->destinationDivision?->name ?? '-';
        $reviewer = $this->incomingLetter->review?->reviewer;
        $sender = $this->incomingLetter->sender_name ?: '-';
        $subject = $this->incomingLetter->subject ?: '-';
        $reviewerName = $reviewer?->name ?? '-';

        return [
            'kind' => 'incoming_letter_forwarded',
            'title' => "Surat Masuk Diteruskan ke Divisi {$divisionName}",
            'message' => "Surat Masuk dari {$sender} dengan perihal {$subject} telah diperiksa oleh {$reviewerName} dan diteruskan ke Divisi {$divisionName}.",
            'incoming_letter_id' => $this->incomingLetter->id,
            'destination_division_id' => $this->incomingLetter->destination_division_id,
            'reviewer_id' => $reviewer?->id,
            'icon' => 'fa-solid fa-share-from-square',
        ];
    }
}
