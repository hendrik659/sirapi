<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterInitialAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'setup_code' => [
                'required',
                'string',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'password' => [
                'required',
                'confirmed',
                Password::min(12),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'setup_code.required' =>
                'Setup Code wajib diisi.',

            'name.required' =>
                'Nama Admin wajib diisi.',

            'email.required' =>
                'Email Admin wajib diisi.',

            'email.email' =>
                'Format email tidak valid.',

            'email.unique' =>
                'Email tersebut sudah digunakan.',

            'password.required' =>
                'Kata sandi wajib diisi.',

            'password.confirmed' =>
                'Konfirmasi kata sandi tidak sesuai.',
        ];
    }
}
