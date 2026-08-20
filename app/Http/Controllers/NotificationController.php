<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'filter' => ['nullable', Rule::in(['all', 'unread'])],
        ]);
        $filter = $validated['filter'] ?? 'all';

        $notifications = $request->user()
            ->notifications()
            ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        /** @var DatabaseNotification $userNotification */
        $userNotification = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $userNotification->markAsRead();

        return $this->redirectToDocument($userNotification);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi telah ditandai sebagai dibaca.');
    }

    private function redirectToDocument(DatabaseNotification $notification): RedirectResponse
    {
        $kind = $notification->data['kind'] ?? null;

        if (is_string($kind) && str_starts_with($kind, 'incoming_letter_')) {
            $incomingLetterId = $notification->data['incoming_letter_id'] ?? null;

            if ($incomingLetterId !== null) {
                return redirect()->route('incoming-letters.show', $incomingLetterId);
            }
        }

        if ($kind === 'outgoing_letter_created') {
            $outgoingLetterId = $notification->data['outgoing_letter_id'] ?? null;

            if ($outgoingLetterId !== null) {
                return redirect()->route('outgoing-letters.show', $outgoingLetterId);
            }
        }

        return redirect()->route('notifications.index');
    }
}
