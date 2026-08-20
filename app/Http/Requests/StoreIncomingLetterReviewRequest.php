<?php

namespace App\Http\Requests;

use App\Models\IncomingLetter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreIncomingLetterReviewRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->has('action')) {
            $this->merge(['action' => 'forward']);
        }
    }

    public function authorize(): bool
    {
        $incomingLetter = $this->route('incomingLetter');

        if (! $this->user() || ! $incomingLetter instanceof IncomingLetter) {
            return false;
        }

        Gate::forUser($this->user())->authorize('review', $incomingLetter);

        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['forward', 'archive_directly'])],
            'destination_division_id' => [
                'exclude_if:action,archive_directly',
                'required_if:action,forward',
                'nullable',
                'integer',
                Rule::exists('divisions', 'id')->where('is_active', true),
            ],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Tindakan pemeriksaan wajib dipilih.',
            'action.in' => 'Tindakan pemeriksaan tidak valid.',
            'destination_division_id.required' => 'Divisi tujuan wajib dipilih untuk tindakan penerusan.',
            'destination_division_id.exists' => 'Divisi tujuan tidak tersedia atau sudah tidak aktif.',
        ];
    }
}
