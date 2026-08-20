<?php

namespace Tests\Feature;

use App\Http\Requests\StoreOutgoingLetterRequest;
use App\Models\Division;
use App\Models\OutgoingLetter;
use App\Models\OutgoingLetterHistory;
use App\Models\Role;
use App\Models\User;
use App\Policies\OutgoingLetterPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class OutgoingLetterFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_tables_models_relations_and_casts_are_consistent(): void
    {
        $this->assertTrue(Schema::hasColumns('outgoing_letters', [
            'id',
            'reference_code',
            'letter_number',
            'letter_date',
            'recipient_name',
            'recipient_address',
            'subject',
            'division_id',
            'created_by',
            'document_path',
            'original_document_name',
            'document_mime_type',
            'document_size',
            'created_at',
            'updated_at',
        ]));
        $this->assertFalse(Schema::hasColumn('outgoing_letters', 'archived_at'));
        $this->assertFalse(Schema::hasColumn('outgoing_letters', 'archived_by'));
        $this->assertFalse(Schema::hasColumn('outgoing_letters', 'status'));
        $this->assertTrue(Schema::hasColumns('outgoing_letter_histories', [
            'id',
            'outgoing_letter_id',
            'activity',
            'notes',
            'changed_by',
            'created_at',
        ]));

        $division = $this->makeDivision();
        $creator = $this->makeUser('anggota_divisi', $division);
        $letter = $this->makeLetter($creator, $division, [
            'letter_date' => '2026-08-07',
            'document_size' => 4096,
        ]);
        $older = OutgoingLetterHistory::query()->forceCreate([
            'outgoing_letter_id' => $letter->id,
            'activity' => 'Riwayat lama yang dipertahankan',
            'notes' => null,
            'changed_by' => $creator->id,
            'created_at' => now()->subMinute(),
        ]);
        $newer = OutgoingLetterHistory::query()->forceCreate([
            'outgoing_letter_id' => $letter->id,
            'activity' => 'Surat Keluar dicatat',
            'notes' => null,
            'changed_by' => $creator->id,
            'created_at' => now(),
        ]);

        $this->assertTrue($letter->division->is($division));
        $this->assertTrue($letter->creator->is($creator));
        $this->assertSame('2026-08-07', $letter->letter_date->format('Y-m-d'));
        $this->assertIsInt($letter->division_id);
        $this->assertIsInt($letter->created_by);
        $this->assertIsInt($letter->document_size);
        $this->assertSame([$newer->id, $older->id], $letter->histories()->pluck('id')->all());
        $this->assertTrue($newer->outgoingLetter->is($letter));
        $this->assertTrue($newer->changedBy->is($creator));
        $this->assertContains('document_path', $letter->getHidden());
        $this->assertFalse(method_exists($letter, 'archiver'));
    }

    public function test_cleanup_migration_normalizes_existing_codes_and_removes_archive_columns(): void
    {
        $migration = require database_path('migrations/2026_08_07_200000_finalize_outgoing_letters_workflow.php');
        $migration->down();

        $division = $this->makeDivision();
        $creator = $this->makeUser('anggota_divisi', $division);

        foreach ([1, 25, 125, 1000] as $id) {
            OutgoingLetter::query()->forceCreate([
                'id' => $id,
                'reference_code' => 'OLD-'.$id,
                'letter_number' => 'NUMBER-'.$id,
                'letter_date' => '2026-08-07',
                'recipient_name' => 'Penerima '.$id,
                'recipient_address' => null,
                'subject' => 'Normalisasi kode',
                'division_id' => $division->id,
                'created_by' => $creator->id,
                'document_path' => 'outgoing-letters/2026/'.$id.'.pdf',
                'original_document_name' => $id.'.pdf',
                'document_mime_type' => 'application/pdf',
                'document_size' => 100,
            ]);
        }

        $migration->up();

        $this->assertSame('SK-2026-001', OutgoingLetter::query()->findOrFail(1)->reference_code);
        $this->assertSame('SK-2026-025', OutgoingLetter::query()->findOrFail(25)->reference_code);
        $this->assertSame('SK-2026-125', OutgoingLetter::query()->findOrFail(125)->reference_code);
        $this->assertSame('SK-2026-1000', OutgoingLetter::query()->findOrFail(1000)->reference_code);
        $this->assertFalse(Schema::hasColumn('outgoing_letters', 'archived_at'));
        $this->assertFalse(Schema::hasColumn('outgoing_letters', 'archived_by'));
    }

    public function test_reference_code_and_letter_number_remain_unique(): void
    {
        $division = $this->makeDivision();
        $creator = $this->makeUser('anggota_divisi', $division);
        $existing = $this->makeLetter($creator, $division);

        try {
            $this->makeLetter($creator, $division, [
                'reference_code' => $existing->reference_code,
            ]);
            $this->fail('Duplikasi reference_code seharusnya ditolak.');
        } catch (QueryException) {
            $this->assertTrue(true);
        }

        $this->expectException(QueryException::class);
        $this->makeLetter($creator, $division, [
            'letter_number' => $existing->letter_number,
        ]);
    }

    public function test_store_request_validates_only_create_fields_and_document_rules(): void
    {
        $request = new StoreOutgoingLetterRequest;
        $valid = $this->validPayload([
            'reference_code' => 'FORGED',
            'division_id' => 999,
            'created_by' => 999,
            'archived_at' => now(),
            'archived_by' => 999,
            'status' => 'forged',
        ]);
        $validator = Validator::make($valid, $request->rules(), $request->messages());

        $this->assertTrue($validator->passes());
        $this->assertEqualsCanonicalizing([
            'letter_number',
            'letter_date',
            'recipient_name',
            'recipient_address',
            'subject',
            'document',
        ], array_keys($validator->validated()));

        foreach ([
            null,
            UploadedFile::fake()->create('surat.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            UploadedFile::fake()->create('surat.pdf', 5121, 'application/pdf'),
        ] as $document) {
            $payload = $this->validPayload();

            if ($document === null) {
                unset($payload['document']);
            } else {
                $payload['document'] = $document;
            }

            $this->assertTrue(Validator::make(
                $payload,
                $request->rules(),
                $request->messages(),
            )->fails());
        }
    }

    public function test_final_policy_only_allows_read_and_create_capabilities(): void
    {
        $division = $this->makeDivision();
        $creator = $this->makeUser('anggota_divisi', $division);
        $letter = $this->makeLetter($creator, $division);

        foreach (['admin_surat', 'pimpinan', 'ketua_divisi', 'anggota_divisi'] as $roleSlug) {
            $user = $this->makeUser($roleSlug, $division);
            $this->assertTrue(Gate::forUser($user)->allows('viewAny', OutgoingLetter::class));
            $this->assertTrue(Gate::forUser($user)->allows('view', $letter));
            $this->assertSame(
                in_array($roleSlug, ['ketua_divisi', 'anggota_divisi'], true),
                Gate::forUser($user)->allows('create', OutgoingLetter::class),
            );
        }

        $this->assertFalse(Gate::forUser($this->makeUser('anggota_divisi'))->allows('create', OutgoingLetter::class));
        $inactive = $this->makeUser('ketua_divisi', $division, false);
        $this->assertFalse(Gate::forUser($inactive)->allows('view', $letter));
        $this->assertFalse(Gate::forUser($inactive)->allows('create', OutgoingLetter::class));
        $this->assertFalse(method_exists(OutgoingLetterPolicy::class, 'update'));
        $this->assertFalse(method_exists(OutgoingLetterPolicy::class, 'archive'));
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
            'name' => Str::headline($roleSlug),
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
        return OutgoingLetter::query()->create(array_merge([
            'reference_code' => 'REF-'.Str::uuid(),
            'letter_number' => 'NO-'.Str::uuid(),
            'letter_date' => '2026-08-07',
            'recipient_name' => 'Penerima Surat',
            'recipient_address' => null,
            'subject' => 'Surat Keluar Final',
            'division_id' => $division->id,
            'created_by' => $creator->id,
            'document_path' => 'outgoing-letters/2026/'.Str::uuid().'.pdf',
            'original_document_name' => 'surat.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => 100,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'letter_number' => '001/SK/VIII/2026',
            'letter_date' => '2026-08-07',
            'recipient_name' => 'Penerima Surat',
            'recipient_address' => null,
            'subject' => 'Surat Keluar Final',
            'document' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf'),
        ], $overrides);
    }
}
