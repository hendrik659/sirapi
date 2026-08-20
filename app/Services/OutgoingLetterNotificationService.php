<?php

namespace App\Services;

use App\Models\OutgoingLetter;
use App\Models\User;
use App\Notifications\OutgoingLetterCreated;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class OutgoingLetterNotificationService
{
    public function notifyCreatedAfterCommit(int $outgoingLetterId): void
    {
        DB::afterCommit(fn () => $this->sendCreated($outgoingLetterId));
    }

    private function sendCreated(int $outgoingLetterId): void
    {
        try {
            $outgoingLetter = OutgoingLetter::query()
                ->with([
                    'division:id,name',
                    'creator:id,name',
                ])
                ->findOrFail($outgoingLetterId);

            User::query()
                ->where('is_active', true)
                ->eachById(function (User $recipient) use ($outgoingLetter, $outgoingLetterId) {
                    try {
                        app(Dispatcher::class)->sendNow(
                            $recipient,
                            new OutgoingLetterCreated($outgoingLetter),
                            ['database'],
                        );
                    } catch (Throwable $exception) {
                        $this->logFailure($outgoingLetterId, $exception, $recipient->id);
                    }
                });
        } catch (Throwable $exception) {
            $this->logFailure($outgoingLetterId, $exception);
        }
    }

    private function logFailure(
        int $outgoingLetterId,
        Throwable $exception,
        ?int $recipientId = null,
    ): void {
        Log::error('Notifikasi Surat Keluar gagal dikirim.', [
            'outgoing_letter_id' => $outgoingLetterId,
            'notification_event' => 'outgoing_letter_created',
            'notification_channels' => ['database'],
            'recipient_user_id' => $recipientId,
            'exception_class' => $exception::class,
        ]);
    }
}
