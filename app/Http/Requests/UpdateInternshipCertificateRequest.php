<?php

namespace App\Http\Requests;

use App\Models\InternshipCertificate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInternshipCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $certificate = $this->route('certificate');

        return $certificate instanceof InternshipCertificate
            && ($this->user()?->can('update', $certificate) ?? false);
    }

    public function rules(): array
    {
        return [
            'participant_name' => ['required', 'string', 'max:255'],
            'institution_name' => ['required', 'string', 'max:255'],
            'major_name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return StoreInternshipCertificateRequest::validationMessages();
    }
}
