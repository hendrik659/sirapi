<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncomingLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'agenda_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('incoming_letters', 'agenda_number')->ignore($this->route('incomingLetter')),
            ],
            'letter_number' => ['nullable', 'string', 'max:100'],
            'sender_name' => ['required', 'string', 'max:255'],
            'addressed_to' => ['nullable', 'string', 'max:255'],
            'letter_date' => ['nullable', 'date'],
            'received_date' => ['required', 'date'],
            'received_via' => ['required', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:500'],
            'priority' => ['required', 'string', Rule::in(['biasa', 'segera'])],
            'destination_division_id' => ['nullable', 'integer', 'exists:divisions,id'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
