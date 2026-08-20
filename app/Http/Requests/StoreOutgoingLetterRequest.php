<?php

namespace App\Http\Requests;

use App\Models\OutgoingLetter;
use Illuminate\Foundation\Http\FormRequest;

class StoreOutgoingLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', OutgoingLetter::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'letter_number' => ['required', 'string', 'max:100', 'unique:outgoing_letters,letter_number'],
            'letter_date' => ['required', 'date'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_address' => ['nullable', 'string', 'max:2000'],
            'subject' => ['required', 'string', 'max:255'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'letter_number.required' => 'Nomor surat wajib diisi.',
            'letter_number.string' => 'Nomor surat harus berupa teks.',
            'letter_number.max' => 'Nomor surat maksimal 100 karakter.',
            'letter_number.unique' => 'Nomor surat sudah digunakan.',
            'letter_date.required' => 'Tanggal surat wajib diisi.',
            'letter_date.date' => 'Tanggal surat tidak valid.',
            'recipient_name.required' => 'Nama penerima wajib diisi.',
            'recipient_name.string' => 'Nama penerima harus berupa teks.',
            'recipient_name.max' => 'Nama penerima maksimal 255 karakter.',
            'recipient_address.string' => 'Alamat penerima harus berupa teks.',
            'recipient_address.max' => 'Alamat penerima maksimal 2000 karakter.',
            'subject.required' => 'Perihal surat wajib diisi.',
            'subject.string' => 'Perihal surat harus berupa teks.',
            'subject.max' => 'Perihal surat maksimal 255 karakter.',
            'document.required' => 'Dokumen surat wajib diunggah.',
            'document.file' => 'Dokumen surat harus berupa file.',
            'document.mimes' => 'Dokumen surat harus berformat PDF, JPG, JPEG, atau PNG.',
            'document.max' => 'Ukuran dokumen surat maksimal 5 MB.',
        ];
    }
}
