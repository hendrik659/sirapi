<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOutgoingLetterRequest;
use App\Models\Division;
use App\Models\OutgoingLetter;
use App\Services\OutgoingLetterNotificationService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class OutgoingLetterController extends Controller
{
    public function __construct(
        private readonly OutgoingLetterNotificationService $notificationService,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        Gate::authorize('viewAny', OutgoingLetter::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'letter_date' => ['nullable', 'date'],
        ]);

        $outgoingLetters = OutgoingLetter::query()
            ->with($this->relations())
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('reference_code', 'like', "%{$search}%")
                        ->orWhere('letter_number', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%");
                });
            })
            ->when(
                $filters['division_id'] ?? null,
                fn (Builder $query, int $divisionId) => $query->where('division_id', $divisionId),
            )
            ->when(
                $filters['letter_date'] ?? null,
                fn (Builder $query, string $letterDate) => $query->whereDate('letter_date', $letterDate),
            )
            ->orderByDesc('letter_date')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json($outgoingLetters);
        }

        return view('outgoing-letters.index', [
            'outgoingLetters' => $outgoingLetters,
            'divisions' => Division::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): View|JsonResponse
    {
        Gate::authorize('create', OutgoingLetter::class);

        if ($request->expectsJson()) {
            return response()->json([
                'division' => $request->user()->division()->first(['id', 'name', 'code']),
            ]);
        }

        return view('outgoing-letters.form');
    }

    public function store(StoreOutgoingLetterRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('create', OutgoingLetter::class);

        $data = $request->validated();
        $document = $data['document'];
        unset($data['document']);

        $documentPath = $this->storeDocument($document, $data['letter_date']);

        try {
            $outgoingLetter = DB::transaction(function () use ($data, $document, $documentPath, $request) {
                $outgoingLetter = OutgoingLetter::query()->create(array_merge(
                    $data,
                    $this->documentMetadata($document, $documentPath),
                    [
                        'reference_code' => 'TEMP-'.Str::uuid(),
                        'division_id' => $request->user()->division_id,
                        'created_by' => $request->user()->id,
                    ],
                ));

                $outgoingLetter->update([
                    'reference_code' => $this->referenceCode($outgoingLetter),
                ]);

                $outgoingLetter->histories()->create([
                    'activity' => 'Surat Keluar dicatat',
                    'notes' => null,
                    'changed_by' => $request->user()->id,
                ]);

                $this->notificationService->notifyCreatedAfterCommit($outgoingLetter->id);

                return $outgoingLetter;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($documentPath);

            throw $exception;
        }

        $outgoingLetter->refresh()->load($this->relations());

        if ($request->expectsJson()) {
            return response()->json($outgoingLetter, 201);
        }

        return redirect()
            ->route('outgoing-letters.show', $outgoingLetter)
            ->with('success', 'Surat Keluar berhasil disimpan.');
    }

    public function show(Request $request, OutgoingLetter $outgoingLetter): View|JsonResponse
    {
        Gate::authorize('view', $outgoingLetter);

        $outgoingLetter->load($this->relationsWithHistories());

        if ($request->expectsJson()) {
            return response()->json($outgoingLetter);
        }

        return view('outgoing-letters.show', compact('outgoingLetter'));
    }

    public function preview(OutgoingLetter $outgoingLetter): BinaryFileResponse
    {
        Gate::authorize('view', $outgoingLetter);

        $response = response()->file($this->documentAbsolutePath($outgoingLetter), [
            'Content-Type' => $outgoingLetter->document_mime_type,
        ]);

        return $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $outgoingLetter->original_document_name,
            $this->asciiFileName($outgoingLetter->original_document_name),
        );
    }

    public function download(OutgoingLetter $outgoingLetter): BinaryFileResponse
    {
        Gate::authorize('view', $outgoingLetter);

        return response()->download(
            $this->documentAbsolutePath($outgoingLetter),
            $outgoingLetter->original_document_name,
            ['Content-Type' => $outgoingLetter->document_mime_type],
        );
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'division:id,name,code',
            'creator:id,name',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function relationsWithHistories(): array
    {
        return array_merge($this->relations(), [
            'histories.changedBy:id,name',
        ]);
    }

    private function referenceCode(OutgoingLetter $outgoingLetter): string
    {
        return sprintf(
            'SK-%s-%03d',
            $outgoingLetter->letter_date->format('Y'),
            $outgoingLetter->id,
        );
    }

    private function storeDocument(UploadedFile $document, string $letterDate): string
    {
        $year = Carbon::parse($letterDate)->year;
        $extension = Str::lower($document->extension());
        $fileName = Str::uuid()->toString().'.'.$extension;
        $path = $document->storeAs("outgoing-letters/{$year}", $fileName, 'local');

        if ($path === false) {
            throw new \RuntimeException('Dokumen surat keluar gagal disimpan.');
        }

        return $path;
    }

    /**
     * @return array<string, int|string>
     */
    private function documentMetadata(UploadedFile $document, string $path): array
    {
        return [
            'document_path' => $path,
            'original_document_name' => $document->getClientOriginalName(),
            'document_mime_type' => $document->getMimeType() ?: 'application/octet-stream',
            'document_size' => $document->getSize() ?: 0,
        ];
    }

    private function documentAbsolutePath(OutgoingLetter $outgoingLetter): string
    {
        abort_unless(
            Storage::disk('local')->exists($outgoingLetter->document_path),
            404,
            'Dokumen surat keluar tidak ditemukan.',
        );

        return Storage::disk('local')->path($outgoingLetter->document_path);
    }

    private function asciiFileName(string $fileName): string
    {
        return Str::ascii($fileName) ?: 'document';
    }
}
