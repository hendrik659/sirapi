<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInternshipCertificateRequest;
use App\Http\Requests\UpdateInternshipCertificateRequest;
use App\Models\InternshipCertificate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class InternshipCertificateController extends Controller
{
    private const SUPPORTED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', InternshipCertificate::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'digits:4', 'min:1900', 'max:9999'],
        ]);

        $certificates = InternshipCertificate::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('participant_name', 'like', "%{$search}%")
                        ->orWhere('institution_name', 'like', "%{$search}%")
                        ->orWhere('major_name', 'like', "%{$search}%");
                });
            })
            ->when(
                $filters['year'] ?? null,
                fn (Builder $query, int $year) => $query->whereYear('end_date', $year),
            )
            ->orderByDesc('end_date')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('certificates.index', [
            'certificates' => $certificates,
            'years' => $this->availableYears(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', InternshipCertificate::class);

        return view('certificates.form');
    }

    public function store(StoreInternshipCertificateRequest $request): RedirectResponse
    {
        Gate::authorize('create', InternshipCertificate::class);

        $data = $request->validated();
        $document = $data['document'];
        unset($data['document']);

        $documentPath = $this->storeDocument($document, $data['end_date']);

        try {
            $certificate = DB::transaction(function () use ($data, $document, $documentPath, $request) {
                $certificate = InternshipCertificate::query()->create(array_merge(
                    $data,
                    $this->documentMetadata($document, $documentPath),
                    ['created_by' => $request->user()->id],
                ));

                $certificate->update([
                    'archive_code' => $this->archiveCode($certificate),
                ]);

                return $certificate;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($documentPath);

            throw $exception;
        }

        return redirect()
            ->route('dashboard.certificates.show', $certificate)
            ->with('success', 'Sertifikat berhasil ditambahkan.');
    }

    public function show(InternshipCertificate $certificate): View
    {
        Gate::authorize('view', $certificate);

        $certificate->load('creator:id,name');

        return view('certificates.show', compact('certificate'));
    }

    public function edit(InternshipCertificate $certificate): View
    {
        Gate::authorize('update', $certificate);

        return view('certificates.form', compact('certificate'));
    }

    public function update(
        UpdateInternshipCertificateRequest $request,
        InternshipCertificate $certificate,
    ): RedirectResponse {
        Gate::authorize('update', $certificate);

        $data = $request->validated();
        $document = $data['document'] ?? null;
        unset($data['document']);

        $oldDocumentPath = $certificate->document_path;
        $newDocumentPath = null;
        $documentData = [];

        if ($document !== null) {
            $newDocumentPath = $this->storeDocument($document, $data['end_date']);
            $documentData = $this->documentMetadata($document, $newDocumentPath);
        }

        try {
            $updatedCertificate = DB::transaction(function () use ($data, $documentData, $certificate) {
                $lockedCertificate = InternshipCertificate::query()
                    ->lockForUpdate()
                    ->findOrFail($certificate->id);

                Gate::authorize('update', $lockedCertificate);

                $lockedCertificate->update(array_merge($data, $documentData));
                $lockedCertificate->update([
                    'archive_code' => $this->archiveCode($lockedCertificate),
                ]);

                return $lockedCertificate;
            });
        } catch (\Throwable $exception) {
            if ($newDocumentPath !== null) {
                Storage::disk('local')->delete($newDocumentPath);
            }

            throw $exception;
        }

        if ($newDocumentPath !== null) {
            Storage::disk('local')->delete($oldDocumentPath);
        }

        return redirect()
            ->route('dashboard.certificates.show', $updatedCertificate)
            ->with('success', 'Sertifikat berhasil diperbarui.');
    }

    public function preview(InternshipCertificate $certificate): BinaryFileResponse
    {
        Gate::authorize('view', $certificate);
        $this->ensureSupportedMimeType($certificate);

        $response = response()->file($this->documentAbsolutePath($certificate), [
            'Content-Type' => $certificate->document_mime_type,
        ]);

        return $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $certificate->original_document_name,
            $this->asciiFileName($certificate->original_document_name),
        );
    }

    public function download(InternshipCertificate $certificate): BinaryFileResponse
    {
        Gate::authorize('view', $certificate);
        $this->ensureSupportedMimeType($certificate);

        return response()->download(
            $this->documentAbsolutePath($certificate),
            $certificate->original_document_name,
            ['Content-Type' => $certificate->document_mime_type],
        );
    }

    /**
     * @return Collection<int, int>
     */
    private function availableYears(): Collection
    {
        $driver = DB::connection()->getDriverName();
        $yearExpression = match ($driver) {
            'sqlite' => "CAST(strftime('%Y', end_date) AS INTEGER)",
            'pgsql' => 'EXTRACT(YEAR FROM end_date)',
            default => 'YEAR(end_date)',
        };

        return InternshipCertificate::query()
            ->selectRaw("{$yearExpression} AS certificate_year")
            ->distinct()
            ->orderByDesc('certificate_year')
            ->pluck('certificate_year')
            ->map(fn ($year) => (int) $year);
    }

    private function archiveCode(InternshipCertificate $certificate): string
    {
        return sprintf(
            'SERT-%s-%03d',
            $certificate->end_date->format('Y'),
            $certificate->id,
        );
    }

    private function storeDocument(UploadedFile $document, string $endDate): string
    {
        $year = Carbon::parse($endDate)->year;
        $extension = Str::lower($document->extension());
        $fileName = Str::uuid()->toString().'.'.$extension;
        $path = $document->storeAs("internship-certificates/{$year}", $fileName, 'local');

        abort_if($path === false, 500, 'Dokumen sertifikat gagal disimpan.');

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

    private function documentAbsolutePath(InternshipCertificate $certificate): string
    {
        abort_unless(
            Storage::disk('local')->exists($certificate->document_path),
            404,
            'Dokumen sertifikat tidak ditemukan.',
        );

        return Storage::disk('local')->path($certificate->document_path);
    }

    private function ensureSupportedMimeType(InternshipCertificate $certificate): void
    {
        abort_unless(
            in_array($certificate->document_mime_type, self::SUPPORTED_MIME_TYPES, true),
            415,
            'Format dokumen sertifikat tidak didukung.',
        );
    }

    private function asciiFileName(string $fileName): string
    {
        return Str::ascii($fileName) ?: 'certificate';
    }
}
