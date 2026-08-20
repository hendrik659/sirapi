<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display a searchable, filterable list of users.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'integer', 'exists:roles,id'],
            'division' => ['nullable', 'integer', 'exists:divisions,id'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        $users = User::query()
            ->with(['role', 'division'])
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($filters['role'] ?? null, fn ($query, int $roleId) => $query->where('role_id', $roleId))
            ->when($filters['division'] ?? null, fn ($query, int $divisionId) => $query->where('division_id', $divisionId))
            ->when(
                $filters['status'] ?? null,
                fn ($query, string $status) => $query->where('is_active', $status === 'active'),
            )
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        return view('users.index', [
            'users' => $users,
            'roles' => Role::query()->orderBy('name')->get(),
            'divisions' => Division::query()->orderBy('name')->get(),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('users.form', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['password'] = Hash::make($data['password']);

        $user = User::query()->create($data);

        return redirect()
            ->route('users.show', $user)
            ->with('success', 'Data pengguna berhasil ditambahkan.');
    }

    public function show(User $user): View
    {
        $user->load(['role', 'division']);

        return view('users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        return view('users.form', array_merge(
            $this->formOptions($user),
            compact('user'),
        ));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validatedData($request, $user);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        if ($request->user()->is($user)) {
            $data['is_active'] = true;
        }

        $user->update($data);

        return redirect()
            ->route('users.show', $user)
            ->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        abort_if(
            $request->user()->is($user) && ! $data['is_active'],
            422,
            'Anda tidak dapat menonaktifkan akun sendiri.',
        );

        $user->update(['is_active' => $data['is_active']]);

        return back()->with(
            'success',
            $data['is_active'] ? 'Data pengguna berhasil diaktifkan.' : 'Data pengguna berhasil dinonaktifkan.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?User $user = null): array
    {
        return [
            'roles' => Role::query()->orderBy('name')->get(),
            'divisions' => Division::query()
                ->where(function ($query) use ($user) {
                    $query->where('is_active', true);

                    if ($user?->division_id !== null) {
                        $query->orWhere('id', $user->division_id);
                    }
                })
                ->orderBy('name')
                ->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?User $user = null): array
    {
        $requestedRoleId = $request->input('role_id');
        $role = is_int($requestedRoleId)
            || (is_string($requestedRoleId) && ctype_digit($requestedRoleId))
                ? Role::query()->find((int) $requestedRoleId)
                : null;

        $divisionIsRequired = in_array(
            $role?->slug,
            ['ketua_divisi', 'anggota_divisi'],
            true,
        );

        $allowedDivision = Rule::exists('divisions', 'id')
            ->where(function ($query) use ($user) {
                $query->where(function ($query) use ($user) {
                    $query->where('is_active', true);

                    if ($user?->division_id !== null) {
                        $query->orWhere('id', $user->division_id);
                    }
                });
            });

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:20'],
            'employee_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('users')->ignore($user),
            ],
            'position' => ['nullable', 'string', 'max:100'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'division_id' => [
                Rule::requiredIf($divisionIsRequired),
                'nullable',
                'integer',
                $allowedDivision,
            ],
            'is_active' => ['required', 'boolean'],
            'password' => [
                $user ? 'nullable' : 'required',
                'string',
                'min:8',
                'confirmed',
            ],
        ]);
    }
}
