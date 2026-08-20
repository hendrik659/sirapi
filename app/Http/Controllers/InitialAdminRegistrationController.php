<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterInitialAdminRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InitialAdminRegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.register-admin');
    }

    public function store(
        RegisterInitialAdminRequest $request
    ): RedirectResponse {
        $data = $request->validated();

        $configuredHash = (string) config(
            'sirapi.initial_admin_setup.code_hash'
        );

        $submittedHash = hash(
            'sha256',
            $data['setup_code']
        );

        if (! hash_equals(
            $configuredHash,
            $submittedHash
        )) {
            throw ValidationException::withMessages([
                'setup_code' => 'Setup Code tidak valid.',
            ]);
        }

        DB::transaction(function () use ($data) {

            $adminRole = Role::query()
                ->where('slug', 'admin_surat')
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Cek ulang setelah lock untuk mencegah
             * dua request membuat dua Admin pertama.
             */
            $adminExists = User::query()
                ->where('role_id', $adminRole->id)
                ->exists();

            if ($adminExists) {
                abort(404);
            }

            User::query()->create([
                'name' => trim($data['name']),

                'email' => strtolower(
                    trim($data['email'])
                ),

                'password' => Hash::make(
                    $data['password']
                ),

                'role_id' => $adminRole->id,

                'division_id' => null,

                'is_active' => true,
            ]);
        });

        return redirect()
            ->route('login')
            ->with(
                'success',
                'Admin pertama berhasil dibuat. Silakan masuk ke SIRAPI.'
            );
    }
}
