<?php

namespace App\Http\Requests;

use App\Services\ReportQueryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncomingLetterReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->user() && ! app(ReportQueryService::class)->hasGlobalScope($this->user())) {
            $this->query->remove('division_id');
            $this->request->remove('division_id');
        }
    }

    public function authorize(): bool
    {
        return $this->user() !== null
            && app(ReportQueryService::class)->canAccess($this->user());
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(['baru_diterima', 'menunggu_pemeriksaan', 'selesai'])],
            'priority' => ['nullable', Rule::in(['biasa', 'segera'])],
            'division_id' => [
                'nullable',
                'integer',
                Rule::exists('divisions', 'id')->where('is_active', true),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'search.string' => 'Pencarian harus berupa teks.',
            'search.max' => 'Pencarian maksimal 255 karakter.',
            'start_date.date' => 'Tanggal awal tidak valid.',
            'end_date.date' => 'Tanggal akhir tidak valid.',
            'end_date.after_or_equal' => 'Tanggal akhir harus sama dengan atau setelah tanggal awal.',
            'status.in' => 'Status surat masuk tidak valid.',
            'priority.in' => 'Prioritas surat masuk tidak valid.',
            'division_id.integer' => 'Divisi harus berupa pilihan yang valid.',
            'division_id.exists' => 'Divisi tidak tersedia atau sudah tidak aktif.',
        ];
    }
}
