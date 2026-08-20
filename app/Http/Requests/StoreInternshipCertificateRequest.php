<?php

namespace App\Http\Requests;

use App\Models\InternshipCertificate;
use Illuminate\Foundation\Http\FormRequest;

class StoreInternshipCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InternshipCertificate::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'participant_name' => ['required', 'string', 'max:255'],
            'institution_name' => ['required', 'string', 'max:255'],
            'major_name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return self::validationMessages();
    }

    public static function validationMessages(): array
    {
        return [
            'participant_name.required' => 'Nama peserta wajib diisi.',
            'participant_name.string' => 'Nama peserta harus berupa teks.',
            'participant_name.max' => 'Nama peserta maksimal 255 karakter.',
            'institution_name.required' => 'Asal institusi wajib diisi.',
            'institution_name.string' => 'Asal institusi harus berupa teks.',
            'institution_name.max' => 'Asal institusi maksimal 255 karakter.',
            'major_name.required' => 'Program studi atau jurusan wajib diisi.',
            'major_name.string' => 'Program studi atau jurusan harus berupa teks.',
            'major_name.max' => 'Program studi atau jurusan maksimal 255 karakter.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.date' => 'Tanggal mulai tidak valid.',
            'end_date.required' => 'Tanggal selesai wajib diisi.',
            'end_date.date' => 'Tanggal selesai tidak valid.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'document.required' => 'Dokumen sertifikat wajib diunggah.',
            'document.file' => 'Dokumen sertifikat harus berupa file.',
            'document.mimes' => 'Dokumen sertifikat harus berformat PDF, JPG, JPEG, atau PNG.',
            'document.max' => 'Ukuran dokumen sertifikat maksimal 5 MB.',
        ];
    }
}
