<?php

namespace App\Notifications;

use App\Models\OutgoingLetter;
use Illuminate\Notifications\Notification;

class OutgoingLetterCreated extends Notification
{
    public function __construct(public readonly OutgoingLetter $outgoingLetter) {}

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
        $letterNumber = $this->outgoingLetter->letter_number ?: $this->outgoingLetter->reference_code;
        $divisionName = $this->outgoingLetter->division?->name ?? '-';
        $creatorName = $this->outgoingLetter->creator?->name ?? '-';

        return [
            'kind' => 'outgoing_letter_created',
            'title' => 'Surat Keluar Baru Diarsipkan',
            'message' => "Surat Keluar nomor {$letterNumber} dari Divisi {$divisionName} telah diarsipkan oleh {$creatorName}.",
            'outgoing_letter_id' => $this->outgoingLetter->id,
            'created_by' => $this->outgoingLetter->created_by,
            'division_id' => $this->outgoingLetter->division_id,
            'icon' => 'fa-solid fa-paper-plane',
        ];
    }
}
