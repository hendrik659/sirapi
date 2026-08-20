<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\OutgoingLetter;
use App\Models\OutgoingLetterHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class OutgoingLetterBackendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_guest_and_inactive_user_cannot_access_final_routes(): void
    {
        $division = $this->makeDivision();
        $creator = $this->makeUser('anggota_divisi', $division);
        $inactive = $this->makeUser('anggota_divisi', $division, false);
        $letter = $this->makeLetter($creator, $division);
        $requests = [
            fn () => $this->get(route('outgoing-letters.index')),
            fn () => $this->get(route('outgoing-letters.create')),
            fn () => $this->post(route('outgoing-letters.store')),
            fn () => $this->get(route('outgoing-letters.show', $letter)),
            fn () => $this->get(route('outgoing-letters.preview', $letter)),
            fn () => $this->get(route('outgoing-letters.download', $letter)),
        ];

        foreach ($requests as $request) {
            $request()->assertRedirect(route('login'));
        }

        foreach ($requests as $request) {
            $this->actingAs($inactive);
            $request()->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_every_active_role_can_read_final_letters_and_files(): void
    {
        $division = $this->makeDivision();
        $creator = $this->makeUser('anggota_divisi', $division);
        $letter = $this->makeLetter($creator, $division);

        foreach (['admin_surat', 'pimpinan', 'ketua_divisi', 'anggota_divisi'] as $roleSlug) {
            $user = $this->makeUser($roleSlug, $division);

            $this->actingAs($user)
                ->getJson(route('outgoing-letters.index'))
                ->assertOk()
                ->assertJsonPath('data.0.id', $letter->id);
            $this->actingAs($user)
                ->getJson(route('outgoing-letters.show', $letter))
                ->assertOk()
                ->assertJsonPath('id', $letter->id);
            $this->actingAs($user)
                ->get(route('outgoing-letters.preview', $letter))
                ->assertOk()
                ->assertHeaderContains('content-disposition', 'inline');
            $this->actingAs($user)
                ->get(route('outgoing-letters.download', $letter))
                ->assertOk()
                ->assertDownload('surat-final.pdf');
        }
    }

    public function test_only_division_head_and_member_with_division_can_store(): void
    {
        $division = $this->makeDivision();

        foreach (['ketua_divisi', 'anggota_divisi'] as $index => $roleSlug) {
            $user = $this->makeUser($roleSlug, $division);

            $this->actingAs($user)
                ->postJson(route('outgoing-letters.store'), $this->storePayload([
                    'letter_number' => 'AUTHORIZED-'.$index,
                ]))
                ->assertCreated()
                ->assertJsonPath('division_id', $division->id)
                ->assertJsonPath('created_by', $user->id);
        }

        foreach (['admin_surat', 'pimpinan'] as $index => $roleSlug) {
            $user = $this->makeUser($roleSlug, $division);

            $this->actingAs($user)
                ->postJson(route('outgoing-letters.store'), $this->storePayload([
                    'letter_number' => 'FORBIDDEN-'.$index,
                ]))
                ->assertForbidden();
            $this->actingAs($user)
                ->postJson(route('outgoing-letters.store'), [])
                ->assertForbidden()
                ->assertJsonMissingValidationErrors('letter_number');
        }

        $divisionless = $this->makeUser('anggota_divisi');
        $this->actingAs($divisionless)
            ->postJson(route('outgoing-letters.store'), $this->storePayload([
                'letter_number' => 'NO-DIVISION',
            ]))
            ->assertForbidden();

        $this->assertDatabaseCount('outgoing_letters', 2);
    }

    public function test_store_sets_final_internal_fields_reference_code_file_and_single_history(): void
    {
        $division = $this->makeDivision('Redaksi', 'RED');
        $otherDivision = $this->makeDivision('Keuangan', 'KEU');
        $creator = $this->makeUser('anggota_divisi', $division);
        $otherUser = $this->makeUser('ketua_divisi', $otherDivision);

        $this->actingAs($creator)
            ->postJson(route('outgoing-letters.store'), $this->storePayload([
                'letter_number' => '001/RED/VIII/2026',
                'letter_date' => '2026-08-07',
                'document' => UploadedFile::fake()->create('final.pdf', 120, 'application/pdf'),
                'reference_code' => 'FORGED',
                'division_id' => $otherDivision->id,
                'created_by' => $otherUser->id,
                'archived_at' => now(),
                'archived_by' => $otherUser->id,
                'status' => 'forged',
            ]))
            ->assertCreated()
            ->assertJsonMissingPath('document_path');

        $letter = OutgoingLetter::query()->firstOrFail();

        $this->assertSame('SK-2026-001', $letter->reference_code);
        $this->assertSame($division->id, $letter->division_id);
        $this->assertSame($creator->id, $letter->created_by);
        $this->assertStringStartsWith('outgoing-letters/2026/', $letter->document_path);
        $this->assertMatchesRegularExpression('#/[0-9a-f-]{36}\.pdf\z#', $letter->document_path);
        $this->assertSame('final.pdf', $letter->original_document_name);
        $this->assertSame('application/pdf', $letter->document_mime_type);
        Storage::disk('local')->assertExists($letter->document_path);
        $this->assertDatabaseHas('outgoing_letter_histories', [
            'outgoing_letter_id' => $letter->id,
            'activity' => 'Surat Keluar dicatat',
            'changed_by' => $creator->id,
        ]);
        $this->assertDatabaseCount('outgoing_letter_histories', 1);
    }

    public function test_reference_code_uses_letter_year_and_database_id_with_three_digit_minimum(): void
    {
        $division = $this->makeDivision();
        $creator = $this->makeUser('anggota_divisi', $division);
        OutgoingLetter::query()->forceCreate($this->letterAttributes($creator, $division, [
            'id' => 999,
            'reference_code' => 'SEED-999',
            'letter_number' => 'SEED-999',
        ]));

        $this->actingAs($creator)
            ->postJson(route('outgoing-letters.store'), $this->storePayload([
                'letter_number' => 'ID-1000',
                'letter_date' => '2027-01-02',
            ]))
            ->assertCreated();

        $letter = OutgoingLetter::query()->findOrFail(1000);
        $this->assertSame('SK-2027-1000', $letter->reference_code);
    }

    public function test_failed_store_rolls_back_record_and_history_and_removes_new_file(): void
    {
        $division = $this->makeDivision();
        $creator = $this->makeUser('anggota_divisi', $division);
        OutgoingLetterHistory::creating(function (): void {
            throw new RuntimeException('Simulasi kegagalan history.');
        });

        try {
            $response = $this->actingAs($creator)
                ->postJson(route('outgoing-letters.store'), $this->storePayload());
        } finally {
            OutgoingLetterHistory::flushEventListeners();
        }

        $response->assertServerError();
        $this->assertDatabaseCount('outgoing_letters', 0);
        $this->assertDatabaseCount('outgoing_letter_histories', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('outgoing-letters'));
    }

    public function test_preview_download_and_missing_file_handling_remain_private(): void
    {
        $division = $this->makeDivision();
        $creator = $this->makeUser('anggota_divisi', $division);
        $reader = $this->makeUser('pimpinan');
        $letter = $this->makeLetter($creator, $division);

        $this->actingAs($reader)
            ->get(route('outgoing-letters.preview', $letter))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeaderContains('content-disposition', 'inline');
        $this->actingAs($reader)
            ->get(route('outgoing-letters.download', $letter))
            ->assertOk()
            ->assertDownload('surat-final.pdf');

        Storage::disk('local')->delete($letter->document_path);

        $this->actingAs($reader)->get(route('outgoing-letters.preview', $letter))->assertNotFound();
        $this->actingAs($reader)->get(route('outgoing-letters.download', $letter))->assertNotFound();
    }

    public function test_document_path_is_hidden_from_all_json_responses(): void
    {
        $division = $this->makeDivision();
        $creator = $this->makeUser('anggota_divisi', $division);
        $letter = $this->makeLetter($creator, $division);

        $this->actingAs($creator)
            ->getJson(route('outgoing-letters.index'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.document_path');
        $this->actingAs($creator)
            ->getJson(route('outgoing-letters.show', $letter))
            ->assertOk()
            ->assertJsonMissingPath('document_path');
    }

    public function test_search_division_date_filters_combination_and_pagination_work(): void
    {
        $redaction = $this->makeDivision('Redaksi', 'RED');
        $finance = $this->makeDivision('Keuangan', 'KEU');
        $creator = $this->makeUser('anggota_divisi', $redaction);
        $letters = [
            $this->makeLetter($creator, $redaction, ['reference_code' => 'REF-SEARCH-UNIQUE']),
            $this->makeLetter($creator, $redaction, ['letter_number' => 'NUMBER-SEARCH-UNIQUE']),
            $this->makeLetter($creator, $redaction, ['recipient_name' => 'RECIPIENT-SEARCH-UNIQUE']),
            $this->makeLetter($creator, $redaction, ['subject' => 'SUBJECT-SEARCH-UNIQUE']),
        ];

        foreach ([
            'REF-SEARCH-UNIQUE' => $letters[0]->id,
            'NUMBER-SEARCH-UNIQUE' => $letters[1]->id,
            'RECIPIENT-SEARCH-UNIQUE' => $letters[2]->id,
            'SUBJECT-SEARCH-UNIQUE' => $letters[3]->id,
        ] as $search => $expectedId) {
            $this->actingAs($creator)
                ->getJson(route('outgoing-letters.index', ['search' => $search]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $expectedId);
        }

        $target = $this->makeLetter($creator, $finance, [
            'subject' => 'Kombinasi Pagination',
            'letter_date' => '2026-08-06',
        ]);
        $this->makeLetter($creator, $redaction, [
            'subject' => 'Kombinasi Pagination',
            'letter_date' => '2026-08-06',
        ]);
        $this->makeLetter($creator, $finance, [
            'subject' => 'Kombinasi Pagination',
            'letter_date' => '2026-08-07',
        ]);

        $this->actingAs($creator)
            ->getJson(route('outgoing-letters.index', [
                'search' => 'Kombinasi',
                'division_id' => $finance->id,
                'letter_date' => '2026-08-06',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id);

        foreach (range(1, 11) as $number) {
            $this->makeLetter($creator, $redaction, [
                'letter_number' => 'PAGINATION-'.$number,
                'subject' => 'Pagination Final',
                'letter_date' => '2026-08-07',
            ]);
        }

        $response = $this->actingAs($creator)
            ->getJson(route('outgoing-letters.index', [
                'search' => 'Pagination Final',
                'division_id' => $redaction->id,
                'letter_date' => '2026-08-07',
            ]))
            ->assertOk()
            ->assertJsonCount(10, 'data');

        $nextPage = $response->json('next_page_url');
        $this->assertStringContainsString('search=Pagination', $nextPage);
        $this->assertStringContainsString('division_id='.$redaction->id, $nextPage);
        $this->assertStringContainsString('letter_date=2026-08-07', $nextPage);
    }

    public function test_final_runtime_exposes_exactly_six_routes_without_mutation_actions(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with($route->getName() ?? '', 'outgoing-letters.'));

        $this->assertEqualsCanonicalizing([
            'outgoing-letters.index',
            'outgoing-letters.create',
            'outgoing-letters.store',
            'outgoing-letters.show',
            'outgoing-letters.preview',
            'outgoing-letters.download',
        ], $routes->pluck('action.as')->all());
        $this->assertCount(6, $routes);

        foreach (['edit', 'update', 'archive', 'destroy', 'unarchive', 'approval', 'revision', 'delivery'] as $action) {
            $this->assertFalse(Route::has('outgoing-letters.'.$action));
        }
    }

    private function makeDivision(string $name = 'Redaksi', string $code = 'RED'): Division
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeLetter(User $creator, Division $division, array $overrides = []): OutgoingLetter
    {
        $path = 'outgoing-letters/2026/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 outgoing letter');

        return OutgoingLetter::query()->create($this->letterAttributes($creator, $division, array_merge([
            'document_path' => $path,
            'document_size' => Storage::disk('local')->size($path),
        ], $overrides)));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function letterAttributes(User $creator, Division $division, array $overrides = []): array
    {
        return array_merge([
            'reference_code' => 'REF-'.Str::uuid(),
            'letter_number' => 'NO-'.Str::uuid(),
            'letter_date' => '2026-08-07',
            'recipient_name' => 'Penerima Surat',
            'recipient_address' => null,
            'subject' => 'Surat Keluar Final',
            'division_id' => $division->id,
            'created_by' => $creator->id,
            'document_path' => 'outgoing-letters/2026/'.Str::uuid().'.pdf',
            'original_document_name' => 'surat-final.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => 100,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storePayload(array $overrides = []): array
    {
        return array_merge([
            'letter_number' => '001/SK/VIII/2026',
            'letter_date' => '2026-08-07',
            'recipient_name' => 'Penerima Surat',
            'recipient_address' => 'Jalan Penerima Nomor 1',
            'subject' => 'Surat Keluar Final',
            'document' => UploadedFile::fake()->create('surat-final.pdf', 100, 'application/pdf'),
        ], $overrides);
    }
}
