<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreIncomingLetterReviewRequest;
use App\Models\Division;
use App\Models\IncomingLetter;
use App\Services\IncomingLetterNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class IncomingLetterReviewController extends Controller
{
    public function __construct(
        private readonly IncomingLetterNotificationService $notificationService,
    ) {}

    public function create(IncomingLetter $incomingLetter): View
    {
        Gate::authorize('review', $incomingLetter);
        $this->ensureReviewable($incomingLetter);

        $incomingLetter->load([
            'creator:id,name',
            'review.reviewer:id,name',
            'review.destinationDivision:id,name,code',
            'destinationDivision:id,name,code',
            'statusHistories.changedBy:id,name',
        ]);

        return view('incoming-letters.review', [
            'incomingLetter' => $incomingLetter,
            'divisions' => Division::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function store(
        StoreIncomingLetterReviewRequest $request,
        IncomingLetter $incomingLetter,
    ): RedirectResponse {
        Gate::authorize('review', $incomingLetter);

        $data = $request->validated();
        $reviewerId = $request->user()->id;
        $reviewerName = $request->user()->name;
        $action = $data['action'];

        $reviewedLetter = DB::transaction(function () use ($action, $data, $incomingLetter, $reviewerId, $reviewerName) {
            $lockedLetter = IncomingLetter::query()
                ->lockForUpdate()
                ->findOrFail($incomingLetter->id);

            $this->ensureReviewable($lockedLetter);

            $destinationDivision = null;

            if ($action === 'forward') {
                $destinationDivision = Division::query()
                    ->whereKey($data['destination_division_id'])
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                abort_unless(
                    $destinationDivision !== null,
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    'Divisi tujuan tidak tersedia atau sudah tidak aktif.',
                );
            }

            $reviewNote = $data['review_note'] ?? null;

            $lockedLetter->review()->create([
                'reviewed_by' => $reviewerId,
                'destination_division_id' => $destinationDivision?->id,
                'review_note' => $reviewNote,
                'reviewed_at' => now(),
            ]);

            $lockedLetter->update([
                'destination_division_id' => $destinationDivision?->id,
                'status' => IncomingLetter::STATUS_SELESAI,
            ]);

            $activity = $action === 'archive_directly'
                ? "Surat diarsipkan langsung oleh {$reviewerName} tanpa diteruskan ke divisi."
                : "Surat diperiksa dan diteruskan ke Divisi {$destinationDivision->name}";

            $lockedLetter->statusHistories()->create([
                'previous_status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
                'new_status' => IncomingLetter::STATUS_SELESAI,
                'activity' => $activity,
                'notes' => $reviewNote,
                'changed_by' => $reviewerId,
            ]);

            if ($action === 'archive_directly') {
                $this->notificationService->notifyArchivedDirectlyAfterCommit($lockedLetter->id);
            } else {
                $this->notificationService->notifyForwardedToDivisionAfterCommit($lockedLetter->id);
            }

            return $lockedLetter;
        });

        $successMessage = $action === 'archive_directly'
            ? 'Surat Masuk berhasil diarsipkan langsung.'
            : 'Surat Masuk berhasil diperiksa dan diteruskan ke divisi tujuan.';

        return redirect()
            ->route('incoming-letters.show', $reviewedLetter)
            ->with('success', $successMessage);
    }

    private function ensureReviewable(IncomingLetter $incomingLetter): void
    {
        abort_unless(
            $incomingLetter->status === IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Surat masuk tidak berada pada status menunggu pemeriksaan.',
        );

        abort_if(
            $incomingLetter->review()->exists(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Surat masuk sudah diperiksa.',
        );
    }
}
