<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\IncomingLetter;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncomingLetterBackendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_guests_cannot_access_incoming_letter_routes(): void
    {
        $this->get(route('incoming-letters.index'))
            ->assertRedirect(route('login'));
    }

    public function test_an_active_non_admin_can_only_read_incoming_letters(): void
    {
        $reader = $this->makeUser('anggota_divisi', 'Anggota Divisi');
        $letter = $this->makeLetter($reader);

        $this->actingAs($reader)
            ->get(route('incoming-letters.show', $letter))
            ->assertOk();

        $this->actingAs($reader)
            ->get(route('incoming-letters.create'))
            ->assertForbidden();
    }

    public function test_admin_surat_can_create_show_edit_and_update_an_incoming_letter(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $division = $this->makeDivision();

        $this->actingAs($admin)
            ->getJson(route('incoming-letters.create'))
            ->assertOk()
            ->assertJsonPath('divisions.0.id', $division->id);

        $created = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('incoming-letters.store'), $this->letterPayload([
                'agenda_number' => 'AGD-001',
                'destination_division_id' => $division->id,
            ]));

        $created->assertCreated()
            ->assertJsonPath('agenda_number', 'AGD-001')
            ->assertJsonPath('status', IncomingLetter::STATUS_BARU_DITERIMA);

        $letter = IncomingLetter::query()->firstOrFail();

        $this->actingAs($admin)
            ->getJson(route('incoming-letters.show', $letter))
            ->assertOk()
            ->assertJsonPath('creator.id', $admin->id)
            ->assertJsonPath('destination_division.id', $division->id);

        $this->actingAs($admin)
            ->getJson(route('incoming-letters.edit', $letter))
            ->assertOk()
            ->assertJsonPath('incoming_letter.id', $letter->id);

        $previousPath = $letter->document_path;

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->put(route('incoming-letters.update', $letter), $this->letterPayload([
                'agenda_number' => 'AGD-001-REV',
                'subject' => 'Perihal Diperbarui',
                'destination_division_id' => $division->id,
                'document' => UploadedFile::fake()->create('revisi.pdf', 20, 'application/pdf'),
            ]))
            ->assertOk()
            ->assertJsonPath('agenda_number', 'AGD-001-REV');

        $this->assertDatabaseHas('incoming_letters', [
            'id' => $letter->id,
            'agenda_number' => 'AGD-001-REV',
            'subject' => 'Perihal Diperbarui',
            'created_by' => $admin->id,
        ]);
        Storage::disk('local')->assertMissing($previousPath);
    }

    public function test_admin_can_create_an_incoming_letter_with_or_without_the_optional_addressed_to(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $withoutAddressedTo = $this->letterPayload([
            'agenda_number' => 'AGD-TUJUAN-OPSIONAL',
        ]);
        unset($withoutAddressedTo['addressed_to']);

        $this->actingAs($admin)
            ->postJson(route('incoming-letters.store'), $withoutAddressedTo)
            ->assertCreated()
            ->assertJsonPath('addressed_to', null);

        $letterWithoutAddressedTo = IncomingLetter::query()
            ->where('agenda_number', 'AGD-TUJUAN-OPSIONAL')
            ->firstOrFail();

        $this->assertNull($letterWithoutAddressedTo->addressed_to);

        $this->actingAs($admin)
            ->get(route('incoming-letters.show', $letterWithoutAddressedTo))
            ->assertOk()
            ->assertSee('Tidak dicantumkan');

        $this->actingAs($admin)
            ->postJson(route('incoming-letters.store'), $this->letterPayload([
                'agenda_number' => 'AGD-TUJUAN-NULL',
                'addressed_to' => null,
            ]))
            ->assertCreated()
            ->assertJsonPath('addressed_to', null);

        $this->assertDatabaseHas('incoming_letters', [
            'agenda_number' => 'AGD-TUJUAN-NULL',
            'addressed_to' => null,
        ]);

        $this->actingAs($admin)
            ->postJson(route('incoming-letters.store'), $this->letterPayload([
                'agenda_number' => 'AGD-TUJUAN-TERISI',
                'addressed_to' => 'Pimpinan Jawa Pos Radar Kediri',
            ]))
            ->assertCreated()
            ->assertJsonPath('addressed_to', 'Pimpinan Jawa Pos Radar Kediri');

        $this->assertDatabaseHas('incoming_letters', [
            'agenda_number' => 'AGD-TUJUAN-TERISI',
            'addressed_to' => 'Pimpinan Jawa Pos Radar Kediri',
        ]);
    }

    public function test_optional_addressed_to_still_enforces_length_and_other_required_fields(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');

        $this->actingAs($admin)
            ->postJson(route('incoming-letters.store'), $this->letterPayload([
                'addressed_to' => str_repeat('a', 256),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('addressed_to');

        $payload = $this->letterPayload(['addressed_to' => null]);
        unset($payload['sender_name']);

        $this->actingAs($admin)
            ->postJson(route('incoming-letters.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sender_name');
    }

    public function test_admin_can_clear_addressed_to_when_updating_an_editable_letter(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $letter = $this->makeLetter($admin, [
            'addressed_to' => 'Tujuan Surat Lama',
        ]);
        $payload = $this->letterPayload([
            'agenda_number' => $letter->agenda_number,
        ]);
        unset($payload['addressed_to'], $payload['document']);

        $this->actingAs($admin)
            ->putJson(route('incoming-letters.update', $letter), $payload)
            ->assertOk()
            ->assertJsonPath('addressed_to', null);

        $this->assertNull($letter->fresh()->addressed_to);
    }

    public function test_upload_is_stored_privately_with_its_metadata(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('incoming-letters.store'), $this->letterPayload([
                'agenda_number' => 'AGD-UPLOAD',
                'received_date' => '2026-08-03',
                'document' => UploadedFile::fake()->create('dokumen-masuk.pdf', 100, 'application/pdf'),
            ]))
            ->assertCreated();

        $letter = IncomingLetter::query()->where('agenda_number', 'AGD-UPLOAD')->firstOrFail();

        Storage::disk('local')->assertExists($letter->document_path);
        $this->assertStringStartsWith('incoming-letters/2026/', $letter->document_path);
        $this->assertSame('dokumen-masuk.pdf', $letter->original_document_name);
        $this->assertSame('application/pdf', $letter->document_mime_type);
        $this->assertGreaterThan(0, $letter->document_size);
    }

    public function test_update_without_a_new_document_keeps_the_existing_document(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $letter = $this->makeLetter($admin);
        $originalDocument = [
            'path' => $letter->document_path,
            'name' => $letter->original_document_name,
            'mime_type' => $letter->document_mime_type,
            'size' => $letter->document_size,
        ];
        $payload = $this->letterPayload([
            'agenda_number' => $letter->agenda_number,
            'subject' => 'Perihal Tanpa Dokumen Baru',
        ]);
        unset($payload['document']);

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->put(route('incoming-letters.update', $letter), $payload)
            ->assertOk()
            ->assertJsonPath('subject', 'Perihal Tanpa Dokumen Baru');

        $letter->refresh();

        $this->assertSame($originalDocument['path'], $letter->document_path);
        $this->assertSame($originalDocument['name'], $letter->original_document_name);
        $this->assertSame($originalDocument['mime_type'], $letter->document_mime_type);
        $this->assertSame($originalDocument['size'], $letter->document_size);
        Storage::disk('local')->assertExists($originalDocument['path']);
    }

    public function test_active_user_can_preview_and_download_an_incoming_letter(): void
    {
        $reader = $this->makeUser('anggota_divisi', 'Anggota Divisi');
        $letter = $this->makeLetter($reader, [
            'original_document_name' => 'surat-masuk.pdf',
            'document_mime_type' => 'application/pdf',
        ]);

        $this->actingAs($reader)
            ->get(route('incoming-letters.preview', $letter))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeaderContains('content-disposition', 'inline');

        $this->actingAs($reader)
            ->get(route('incoming-letters.download', $letter))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('surat-masuk.pdf');
    }

    public function test_private_document_path_is_not_exposed_by_letter_json_responses(): void
    {
        $reader = $this->makeUser('anggota_divisi', 'Anggota Divisi');
        $letter = $this->makeLetter($reader);

        $this->actingAs($reader)
            ->getJson(route('incoming-letters.index'))
            ->assertOk()
            ->assertJsonMissingPath('data.0.document_path');

        $this->actingAs($reader)
            ->getJson(route('incoming-letters.show', $letter))
            ->assertOk()
            ->assertJsonMissingPath('document_path');
    }

    public function test_index_searches_and_filters_incoming_letters(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $marketing = $this->makeDivision('Pemasaran', 'PEM');
        $finance = $this->makeDivision('Keuangan', 'KEU');

        $matchingLetter = $this->makeLetter($admin, [
            'agenda_number' => 'AGD-CARI',
            'letter_number' => '001/RS/VIII/2026',
            'sender_name' => 'Kantor Pajak',
            'subject' => 'Undangan Rapat Pajak',
            'priority' => 'segera',
            'status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'destination_division_id' => $marketing->id,
            'received_date' => '2026-08-03',
        ]);
        $this->makeLetter($admin, [
            'agenda_number' => 'AGD-LAIN',
            'sender_name' => 'Pemasok',
            'subject' => 'Kontrak Tahunan',
            'priority' => 'biasa',
            'destination_division_id' => $finance->id,
            'received_date' => '2026-08-02',
        ]);

        $this->actingAs($admin)
            ->getJson(route('incoming-letters.index', ['search' => 'Pajak']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingLetter->id);

        $this->actingAs($admin)
            ->getJson(route('incoming-letters.index', [
                'status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
                'priority' => 'segera',
                'destination_division_id' => $marketing->id,
                'received_date' => '2026-08-03',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingLetter->id);
    }

    public function test_admin_surat_can_submit_a_new_letter_for_review(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');
        $letter = $this->makeLetter($admin);

        $this->actingAs($admin)
            ->patchJson(route('incoming-letters.submit-for-review', $letter))
            ->assertOk()
            ->assertJsonPath('status', IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN);

        $this->assertDatabaseHas('incoming_letters', [
            'id' => $letter->id,
            'status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
        ]);
        $this->assertNotNull($letter->fresh()->submitted_for_review_at);
    }

    public function test_priority_must_be_biasa_or_segera(): void
    {
        $admin = $this->makeUser('admin_surat', 'Admin Surat');

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->post(route('incoming-letters.store'), $this->letterPayload([
                'priority' => 'prioritas_lain',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');

        $this->assertDatabaseCount('incoming_letters', 0);

        $letter = $this->makeLetter($admin);

        $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->put(route('incoming-letters.update', $letter), $this->letterPayload([
                'agenda_number' => $letter->agenda_number,
                'priority' => 'prioritas_lain',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');

        $this->assertSame('biasa', $letter->fresh()->priority);
    }

    private function makeDivision(string $name = 'Redaksi', string $code = 'RED'): Division
    {
        return Division::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
        ]);
    }

    private function makeUser(string $roleSlug, string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => $roleName],
        );

        return User::query()->create([
            'name' => $roleName,
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function letterPayload(array $overrides = []): array
    {
        return array_merge([
            'agenda_number' => 'AGD-'.fake()->unique()->numerify('####'),
            'letter_number' => '001/RS/VIII/2026',
            'sender_name' => 'Instansi Pengirim',
            'addressed_to' => 'Radar Surat',
            'letter_date' => '2026-08-01',
            'received_date' => '2026-08-03',
            'received_via' => 'fisik',
            'subject' => 'Undangan Rapat',
            'priority' => 'biasa',
            'destination_division_id' => null,
            'document' => UploadedFile::fake()->create('surat-masuk.pdf', 20, 'application/pdf'),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeLetter(User $creator, array $overrides = []): IncomingLetter
    {
        $path = 'incoming-letters/2026/'.fake()->uuid().'.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 test document');

        return IncomingLetter::query()->create(array_merge([
            'agenda_number' => 'AGD-'.fake()->unique()->numerify('####'),
            'letter_number' => null,
            'sender_name' => 'Instansi Pengirim',
            'addressed_to' => 'Radar Surat',
            'letter_date' => '2026-08-01',
            'received_date' => '2026-08-03',
            'received_via' => 'fisik',
            'subject' => 'Undangan Rapat',
            'priority' => 'biasa',
            'destination_division_id' => null,
            'document_path' => $path,
            'original_document_name' => 'surat.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => Storage::disk('local')->size($path),
            'status' => IncomingLetter::STATUS_BARU_DITERIMA,
            'created_by' => $creator->id,
            'submitted_for_review_at' => null,
        ], $overrides));
    }
}
