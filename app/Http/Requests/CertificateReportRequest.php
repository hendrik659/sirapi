<?php

namespace App\Http\Requests;

use App\Models\InternshipCertificate;
use Illuminate\Foundation\Http\FormRequest;

class CertificateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', InternshipCertificate::class) ?? false;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable', 'integer', 'digits:4', 'min:1900', 'max:9999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'search.string' => 'Pencarian harus berupa teks.',
            'search.max' => 'Pencarian maksimal 255 karakter.',
            'year.integer' => 'Tahun harus berupa angka.',
            'year.digits' => 'Tahun harus terdiri dari 4 digit.',
            'year.min' => 'Tahun tidak valid.',
            'year.max' => 'Tahun tidak valid.',
        ];
    }
}
