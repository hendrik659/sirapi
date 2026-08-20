<?php

namespace App\Http\Controllers;

use App\Models\Division;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DivisionController extends Controller
{
    public function index(): View
    {
        $divisions = Division::query()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return view('divisions.index', compact('divisions'));
    }

    public function create(): View
    {
        return view('divisions.form');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['is_active'] = true;

        $division = Division::query()->create($data);

        return redirect()
            ->route('divisions.show', $division)
            ->with('success', 'Data divisi berhasil ditambahkan.');
    }

    public function show(Division $division): View
    {
        $division->loadCount('users');
        $division->load([
            'users' => function ($query) {
                $query->with('role')->orderBy('name');
            },
        ]);

        return view('divisions.show', compact('division'));
    }

    public function edit(Division $division): View
    {
        return view('divisions.form', compact('division'));
    }

    public function update(Request $request, Division $division): RedirectResponse
    {
        $data = $this->validatedData($request, $division);

        $division->update($data);

        return redirect()
            ->route('divisions.show', $division)
            ->with('success', 'Data divisi berhasil diperbarui.');
    }

    public function updateStatus(Request $request, Division $division): RedirectResponse
    {
        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);
        $isActive = $request->boolean('is_active');

        abort_if(
            ! $isActive && $division->users()->where('is_active', true)->exists(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Divisi tidak dapat dinonaktifkan karena masih memiliki pengguna aktif.',
        );

        $division->update(['is_active' => $isActive]);

        return back()->with(
            'success',
            $isActive ? 'Data divisi berhasil diaktifkan.' : 'Data divisi berhasil dinonaktifkan.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?Division $division = null): array
    {
        $normalized = [];

        if (is_string($request->input('name'))) {
            $normalized['name'] = trim($request->input('name'));
        }

        if (is_string($request->input('code'))) {
            $normalized['code'] = Str::upper(trim($request->input('code')));
        }

        $request->merge($normalized);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('divisions', 'name')->ignore($division),
            ],
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/\A[A-Z0-9_-]+\z/',
                Rule::unique('divisions', 'code')->ignore($division),
            ],
        ]);

        return $data;
    }
}
