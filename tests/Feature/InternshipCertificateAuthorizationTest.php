<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\InternshipCertificate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternshipCertificateAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_guest_is_redirected_from_every_certificate_endpoint(): void
    {
        $creator = $this->makeUser('ketua_divisi', $this->makeDivision('SDM & Umum', 'SDM'));
        $certificate = $this->makeCertificate($creator);

        foreach ($this->endpointRequests($certificate) as $request) {
            $request()->assertRedirect(route('login'));
        }
    }

    public function test_inactive_user_is_rejected_by_active_middleware(): void
    {
        $division = $this->makeDivision('SDM & Umum', 'SDM');
        $creator = $this->makeUser('ketua_divisi', $division);
        $inactive = $this->makeUser('ketua_divisi', $division, false);
        $certificate = $this->makeCertificate($creator);

        $this->actingAs($inactive)
            ->get(route('dashboard.certificates.index'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_sdm_division_head_can_access_every_supported_endpoint(): void
    {
        $division = $this->makeDivision('SDM & Umum', 'SDM');
        $divisionHead = $this->makeUser('ketua_divisi', $division);
        $certificate = $this->makeCertificate($divisionHead);

        $this->actingAs($divisionHead)->get(route('dashboard.certificates.index'))->assertOk();
        $this->actingAs($divisionHead)->get(route('dashboard.certificates.create'))->assertOk();
        $this->actingAs($divisionHead)
            ->post(route('dashboard.certificates.store'), $this->validPayload(['participant_name' => 'Peserta Baru']))
            ->assertRedirect();
        $this->actingAs($divisionHead)->get(route('dashboard.certificates.show', $certificate))->assertOk();
        $this->actingAs($divisionHead)
            ->get(route('dashboard.certificates.preview', $certificate))
            ->assertOk()
            ->assertHeaderContains('content-disposition', 'inline');
        $this->actingAs($divisionHead)
            ->get(route('dashboard.certificates.download', $certificate))
            ->assertOk()
            ->assertDownload('sertifikat-final.pdf');
        $this->actingAs($divisionHead)->get(route('dashboard.certificates.edit', $certificate))->assertOk();
        $this->actingAs($divisionHead)
            ->put(route('dashboard.certificates.update', $certificate), $this->validMetadata([
                'participant_name' => 'Peserta Diperbarui',
            ]))
            ->assertRedirect(route('dashboard.certificates.show', $certificate));
    }

    public function test_admin_and_pimpinan_have_read_only_access(): void
    {
        $sdm = $this->makeDivision('SDM & Umum', 'SDM');
        $creator = $this->makeUser('ketua_divisi', $sdm);
        $certificate = $this->makeCertificate($creator);

        foreach (['admin_surat', 'pimpinan'] as $roleSlug) {
            $reader = $this->makeUser($roleSlug);

            $this->actingAs($reader)->get(route('dashboard.certificates.index'))->assertOk();
            $this->actingAs($reader)->get(route('dashboard.certificates.show', $certificate))->assertOk();
            $this->actingAs($reader)->get(route('dashboard.certificates.preview', $certificate))->assertOk();
            $this->actingAs($reader)->get(route('dashboard.certificates.download', $certificate))->assertOk();
            $this->actingAs($reader)->get(route('dashboard.certificates.create'))->assertForbidden();
            $this->actingAs($reader)
                ->post(route('dashboard.certificates.store'), $this->validPayload())
                ->assertForbidden();
            $this->actingAs($reader)->get(route('dashboard.certificates.edit', $certificate))->assertForbidden();
            $this->actingAs($reader)
                ->put(route('dashboard.certificates.update', $certificate), $this->validMetadata())
                ->assertForbidden();
        }
    }

    public function test_non_sdm_division_head_and_all_members_are_forbidden_from_every_endpoint(): void
    {
        $sdm = $this->makeDivision('SDM & Umum', 'SDM');
        $otherDivision = $this->makeDivision('Redaksi', 'RED');
        $creator = $this->makeUser('ketua_divisi', $sdm);
        $certificate = $this->makeCertificate($creator);
        $forbiddenUsers = [
            $this->makeUser('ketua_divisi', $otherDivision),
            $this->makeUser('anggota_divisi', $otherDivision),
            $this->makeUser('anggota_divisi', $sdm),
        ];

        foreach ($forbiddenUsers as $user) {
            foreach ($this->endpointRequests($certificate) as $request) {
                $this->actingAs($user);
                $request()->assertForbidden();
            }
        }
    }

    public function test_certificate_routes_have_no_delete_or_workflow_actions(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->getName() ?? '', 'dashboard.certificates.'));

        $this->assertEqualsCanonicalizing([
            'dashboard.certificates.index',
            'dashboard.certificates.create',
            'dashboard.certificates.store',
            'dashboard.certificates.show',
            'dashboard.certificates.edit',
            'dashboard.certificates.update',
            'dashboard.certificates.preview',
            'dashboard.certificates.download',
        ], $routes->pluck('action.as')->all());
        $this->assertCount(8, $routes);

        foreach (['destroy', 'archive', 'unarchive', 'approve', 'reject'] as $action) {
            $this->assertFalse(Route::has('dashboard.certificates.'.$action));
        }
    }

    /**
     * @return array<int, callable>
     */
    private function endpointRequests(InternshipCertificate $certificate): array
    {
        return [
            fn () => $this->get(route('dashboard.certificates.index')),
            fn () => $this->get(route('dashboard.certificates.create')),
            fn () => $this->post(route('dashboard.certificates.store'), $this->validPayload()),
            fn () => $this->get(route('dashboard.certificates.show', $certificate)),
            fn () => $this->get(route('dashboard.certificates.preview', $certificate)),
            fn () => $this->get(route('dashboard.certificates.download', $certificate)),
            fn () => $this->get(route('dashboard.certificates.edit', $certificate)),
            fn () => $this->put(route('dashboard.certificates.update', $certificate), $this->validMetadata()),
        ];
    }

    private function makeDivision(string $name, string $code): Division
    {
        return Division::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function makeUser(string $roleSlug, ?Division $division = null, bool $active = true): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => Str::headline($roleSlug)],
        );

        return User::query()->create([
            'name' => Str::headline($roleSlug).' '.Str::random(5),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'division_id' => $division?->id,
            'is_active' => $active,
        ]);
    }

    private function makeCertificate(User $creator): InternshipCertificate
    {
        $path = 'internship-certificates/2026/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 certificate');

        return InternshipCertificate::query()->create([
            'archive_code' => 'SERT-2026-001',
            'participant_name' => 'Peserta Magang',
            'institution_name' => 'Universitas Brawijaya',
            'major_name' => 'Ilmu Komunikasi',
            'start_date' => '2026-05-01',
            'end_date' => '2026-07-31',
            'document_path' => $path,
            'original_document_name' => 'sertifikat-final.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => Storage::disk('local')->size($path),
            'created_by' => $creator->id,
        ]);
    }

    private function validMetadata(array $overrides = []): array
    {
        return array_merge([
            'participant_name' => 'Peserta Magang',
            'institution_name' => 'Universitas Brawijaya',
            'major_name' => 'Ilmu Komunikasi',
            'start_date' => '2026-05-01',
            'end_date' => '2026-07-31',
        ], $overrides);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge($this->validMetadata(), [
            'document' => UploadedFile::fake()->create('sertifikat.pdf', 100, 'application/pdf'),
        ], $overrides);
    }
}
