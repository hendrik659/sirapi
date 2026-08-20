<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInitialAdminRegistrationAvailable
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        if (! config('sirapi.initial_admin_setup.enabled')) {
            abort(404);
        }

        $codeHash = config(
            'sirapi.initial_admin_setup.code_hash'
        );

        if (! is_string($codeHash) || trim($codeHash) === '') {
            abort(404);
        }

        $adminRoleId = Role::query()
            ->where('slug', 'admin_surat')
            ->value('id');

        if (! $adminRoleId) {
            abort(404);
        }

        $adminExists = User::query()
            ->where('role_id', $adminRoleId)
            ->exists();

        if ($adminExists) {
            abort(404);
        }

        return $next($request);
    }
}
