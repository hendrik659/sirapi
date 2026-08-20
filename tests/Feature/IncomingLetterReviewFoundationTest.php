<?php

namespace Tests\Feature;

use App\Http\Requests\StoreIncomingLetterReviewRequest;
use App\Models\Division;
use App\Models\IncomingLetter;
use App\Models\IncomingLetterReview;
use App\Models\IncomingLetterStatusHistory;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class IncomingLetterReviewFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_obsolete_assignment_table_is_removed(): void
    {
        $this->assertFalse(Schema::hasTable('incoming_letter_assignments'));
    }

    public function test_review_and_status_history_tables_have_the_required_columns_and_indexes(): void
    {
        $this->assertTrue(Schema::hasColumns('incoming_letter_reviews', [
            'id',
            'incoming_letter_id',
            'reviewed_by',
            'destination_division_id',
            'review_note',
            'reviewed_at',
            'created_at',
            'updated_at',
        ]));
        $this->assertTrue(Schema::hasColumns('incoming_letter_status_histories', [
            'id',
            'incoming_letter_id',
            'previous_status',
            'new_status',
            'activity',
            'notes',
            'changed_by',
            'created_at',
        ]));

        $reviewIndexes = collect(Schema::getIndexes('incoming_letter_reviews'));
        $this->assertTrue($reviewIndexes->contains(
            fn (array $index): bool => $index['unique']
                && $index['columns'] === ['incoming_letter_id'],
        ));

        $historyIndexes = collect(Schema::getIndexes('incoming_letter_status_histories'));

        foreach (['incoming_letter_id', 'new_status', 'created_at'] as $column) {
            $this->assertTrue($historyIndexes->contains(
                fn (array $index): bool => $index['columns'] === [$column],
            ));
        }
    }

    public function test_an_incoming_letter_has_one_review_with_its_related_models_and_casts(): void
    {
        $reviewer = $this->makeUser();
        $division = $this->makeDivision();
        $letter = $this->makeLetter($reviewer);

        $review = IncomingLetterReview::query()->create([
            'incoming_letter_id' => $letter->id,
            'reviewed_by' => $reviewer->id,
            'destination_division_id' => $division->id,
            'review_note' => 'Teruskan ke divisi tujuan.',
            'reviewed_at' => '2026-08-03 14:30:00',
        ]);

        $this->assertTrue($letter->review->is($review));
        $this->assertTrue($review->incomingLetter->is($letter));
        $this->assertTrue($review->reviewer->is($reviewer));
        $this->assertTrue($review->destinationDivision->is($division));
        $this->assertSame($letter->id, $review->incoming_letter_id);
        $this->assertSame($reviewer->id, $review->reviewed_by);
        $this->assertSame($division->id, $review->destination_division_id);
        $this->assertSame('2026-08-03 14:30:00', $review->reviewed_at->format('Y-m-d H:i:s'));

        $this->expectException(QueryException::class);

        IncomingLetterReview::query()->create([
            'incoming_letter_id' => $letter->id,
            'reviewed_by' => $reviewer->id,
            'destination_division_id' => $division->id,
            'reviewed_at' => now(),
        ]);
    }

    public function test_status_histories_are_returned_newest_first_with_their_relations(): void
    {
        $user = $this->makeUser();
        $letter = $this->makeLetter($user);

        $olderHistory = IncomingLetterStatusHistory::query()->forceCreate([
            'incoming_letter_id' => $letter->id,
            'previous_status' => null,
            'new_status' => IncomingLetter::STATUS_BARU_DITERIMA,
            'activity' => 'Surat masuk dibuat',
            'notes' => null,
            'changed_by' => $user->id,
            'created_at' => now()->subMinute(),
        ]);
        $newerHistory = IncomingLetterStatusHistory::query()->forceCreate([
            'incoming_letter_id' => $letter->id,
            'previous_status' => IncomingLetter::STATUS_BARU_DITERIMA,
            'new_status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'activity' => 'Surat dikirim untuk pemeriksaan',
            'notes' => 'Menunggu pemeriksaan pimpinan.',
            'changed_by' => $user->id,
            'created_at' => now(),
        ]);

        $histories = $letter->statusHistories()->get();

        $this->assertSame([$newerHistory->id, $olderHistory->id], $histories->pluck('id')->all());
        $this->assertTrue($newerHistory->incomingLetter->is($letter));
        $this->assertTrue($newerHistory->changedBy->is($user));
        $this->assertSame($letter->id, $newerHistory->incoming_letter_id);
        $this->assertSame($user->id, $newerHistory->changed_by);
        $this->assertNotNull($newerHistory->created_at);
    }

    public function test_review_request_only_accepts_an_active_division_and_a_note_up_to_2000_characters(): void
    {
        $activeDivision = $this->makeDivision('Redaksi', 'RED');
        $inactiveDivision = $this->makeDivision('Keuangan', 'KEU', false);
        $request = new StoreIncomingLetterReviewRequest;

        $valid = Validator::make([
            'action' => 'forward',
            'destination_division_id' => $activeDivision->id,
            'review_note' => null,
        ], $request->rules());
        $inactive = Validator::make([
            'action' => 'forward',
            'destination_division_id' => $inactiveDivision->id,
        ], $request->rules());
        $missing = Validator::make(['action' => 'forward'], $request->rules());
        $longNote = Validator::make([
            'action' => 'forward',
            'destination_division_id' => $activeDivision->id,
            'review_note' => str_repeat('a', 2001),
        ], $request->rules());

        $this->assertTrue($valid->passes());
        $this->assertTrue($inactive->fails());
        $this->assertArrayHasKey('destination_division_id', $inactive->errors()->toArray());
        $this->assertTrue($missing->fails());
        $this->assertArrayHasKey('destination_division_id', $missing->errors()->toArray());
        $this->assertTrue($longNote->fails());
        $this->assertArrayHasKey('review_note', $longNote->errors()->toArray());
    }

    private function makeDivision(
        string $name = 'Redaksi',
        string $code = 'RED',
        bool $isActive = true,
    ): Division {
        return Division::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => $isActive,
        ]);
    }

    private function makeUser(): User
    {
        $role = Role::query()->create([
            'name' => 'Pimpinan',
            'slug' => 'pimpinan',
        ]);

        return User::query()->create([
            'name' => 'Pimpinan',
            'email' => 'pimpinan@example.com',
            'password' => 'password',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function makeLetter(User $creator): IncomingLetter
    {
        return IncomingLetter::query()->create([
            'agenda_number' => 'AGD-REVIEW-001',
            'letter_number' => '001/RS/VIII/2026',
            'sender_name' => 'Instansi Pengirim',
            'addressed_to' => 'Radar Surat',
            'letter_date' => '2026-08-01',
            'received_date' => '2026-08-03',
            'received_via' => 'fisik',
            'subject' => 'Permohonan pemeriksaan surat',
            'priority' => 'biasa',
            'document_path' => 'incoming-letters/2026/surat.pdf',
            'original_document_name' => 'surat.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => 1024,
            'status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'created_by' => $creator->id,
            'submitted_for_review_at' => now(),
        ]);
    }
}
