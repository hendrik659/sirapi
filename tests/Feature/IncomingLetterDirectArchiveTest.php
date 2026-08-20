<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\IncomingLetter;
use App\Models\IncomingLetterStatusHistory;
use App\Models\Role;
use App\Models\User;
use App\Notifications\IncomingLetterArchivedDirectly;
use App\Notifications\IncomingLetterForwardedToDivision;
use App\Notifications\IncomingLetterSubmittedForReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class IncomingLetterDirectArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_pimpinan_can_archive_directly_without_a_destination_division(): void
    {
        $admin = $this->makeUser('admin_surat', null, true, 'Admin Surat');
        $pimpinan = $this->makeUser('pimpinan', null, true, 'Pimpinan Aktual');
        $ignoredDivision = $this->makeDivision('Redaksi', 'RED');
        $letter = $this->makeLetter($admin);

        $this->actingAs($pimpinan)
            ->post(route('incoming-letters.review.store', $letter), [
                'action' => 'archive_directly',
                'destination_division_id' => $ignoredDivision->id,
                'review_note' => 'Tidak memerlukan tindak lanjut divisi.',
            ])
            ->assertRedirect(route('incoming-letters.show', $letter))
            ->assertSessionHas('success', 'Surat Masuk berhasil diarsipkan langsung.');

        $review = $letter->review()->firstOrFail();
        $letter->refresh();

        $this->assertSame($pimpinan->id, $review->reviewed_by);
        $this->assertNull($review->destination_division_id);
        $this->assertSame('Tidak memerlukan tindak lanjut divisi.', $review->review_note);
        $this->assertNotNull($review->reviewed_at);
        $this->assertSame(IncomingLetter::STATUS_SELESAI, $letter->status);
        $this->assertNull($letter->destination_division_id);
        $this->assertDatabaseHas('incoming_letter_status_histories', [
            'incoming_letter_id' => $letter->id,
            'previous_status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'new_status' => IncomingLetter::STATUS_SELESAI,
            'activity' => "Surat diarsipkan langsung oleh {$pimpinan->name} tanpa diteruskan ke divisi.",
            'notes' => 'Tidak memerlukan tindak lanjut divisi.',
            'changed_by' => $pimpinan->id,
        ]);

        $this->actingAs($pimpinan)
            ->get(route('incoming-letters.show', $letter))
            ->assertOk()
            ->assertSee('Tidak diteruskan ke divisi')
            ->assertSee("Surat diarsipkan langsung oleh {$pimpinan->name} tanpa diteruskan ke divisi.");
    }

    public function test_sdm_division_head_can_archive_directly(): void
    {
        $sdm = $this->makeDivision('Sumber Daya Manusia', 'SDM');
        $divisionHead = $this->makeUser('ketua_divisi', $sdm);
        $letter = $this->makeLetter($divisionHead);

        $this->actingAs($divisionHead)
            ->post(route('incoming-letters.review.store', $letter), [
                'action' => 'archive_directly',
                'review_note' => null,
            ])
            ->assertRedirect(route('incoming-letters.show', $letter));

        $this->assertDatabaseHas('incoming_letter_reviews', [
            'incoming_letter_id' => $letter->id,
            'reviewed_by' => $divisionHead->id,
            'destination_division_id' => null,
            'review_note' => null,
        ]);
        $this->assertSame(IncomingLetter::STATUS_SELESAI, $letter->fresh()->status);
    }

    public function test_non_sdm_division_head_and_member_cannot_archive_directly(): void
    {
        $nonSdmHead = $this->makeUser('ketua_divisi', $this->makeDivision('Redaksi', 'RED'));
        $member = $this->makeUser('anggota_divisi', $this->makeDivision('Keuangan', 'KEU'));
        $letter = $this->makeLetter($nonSdmHead);

        foreach ([$nonSdmHead, $member] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->post(route('incoming-letters.review.store', $letter), [
                    'action' => 'archive_directly',
                ])
                ->assertForbidden();
        }

        $this->assertReviewDidNotChangeLetter($letter);
    }

    public function test_action_validation_rejects_unknown_action_and_requires_destination_for_forward(): void
    {
        $pimpinan = $this->makeUser('pimpinan');
        $letter = $this->makeLetter($pimpinan);

        $this->actingAs($pimpinan)
            ->post(route('incoming-letters.review.store', $letter), [
                'action' => 'unknown_action',
            ])
            ->assertSessionHasErrors('action');

        $this->actingAs($pimpinan)
            ->post(route('incoming-letters.review.store', $letter), [
                'action' => 'forward',
            ])
            ->assertSessionHasErrors('destination_division_id');

        $this->assertReviewDidNotChangeLetter($letter);
    }

    public function test_direct_archive_notifies_every_active_user_in_app_and_notification_opens_the_letter(): void
    {
        $admin = $this->makeUser('admin_surat', null, true, 'Admin Surat');
        $reviewer = $this->makeUser('pimpinan', null, true, 'Pimpinan Pemeriksa');
        $division = $this->makeDivision('Redaksi', 'RED');
        $activeUsers = [
            $admin,
            $reviewer,
            $this->makeUser('ketua_divisi', $division, true, 'Ketua Redaksi'),
            $this->makeUser('anggota_divisi', $division, true, 'Anggota Redaksi'),
        ];
        $inactiveUser = $this->makeUser('anggota_divisi', $division, false, 'Anggota Nonaktif');
        $letter = $this->makeLetter($admin);

        $this->actingAs($reviewer)
            ->post(route('incoming-letters.review.store', $letter), [
                'action' => 'archive_directly',
            ])
            ->assertRedirect(route('incoming-letters.show', $letter));

        foreach ($activeUsers as $recipient) {
            $this->assertSame(1, $recipient->notifications()->count(), "Notifikasi tidak tersimpan untuk {$recipient->name}.");
        }

        $this->assertSame(0, $inactiveUser->notifications()->count());

        $notification = $admin->notifications()->firstOrFail();
        $payload = $notification->data;
        $this->assertSame('incoming_letter_archived_directly', $payload['kind']);
        $this->assertSame('Surat Masuk Diarsipkan Langsung', $payload['title']);
        $this->assertSame($letter->id, $payload['incoming_letter_id']);
        $this->assertSame($reviewer->id, $payload['reviewer_id']);
        $this->assertStringContainsString('Instansi Pengirim', $payload['message']);
        $this->assertStringContainsString('Permohonan pemeriksaan surat', $payload['message']);
        $this->assertStringContainsString($reviewer->name, $payload['message']);
        $this->assertSame('fa-solid fa-box-archive', $payload['icon']);
        $this->assertStringNotContainsString('document_path', json_encode($payload, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($letter->document_path, json_encode($payload, JSON_THROW_ON_ERROR));

        $this->actingAs($admin)
            ->patch(route('notifications.open', $notification->id))
            ->assertRedirect(route('incoming-letters.show', $letter));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_direct_archive_uses_database_channel_only_and_never_sends_email(): void
    {
        Notification::fake();

        $admin = $this->makeUser('admin_surat');
        $reviewer = $this->makeUser('pimpinan');
        $division = $this->makeDivision();
        $activeUsers = [
            $admin,
            $reviewer,
            $this->makeUser('ketua_divisi', $division),
            $this->makeUser('anggota_divisi', $division),
        ];
        $inactiveUser = $this->makeUser('anggota_divisi', $division, false);
        $letter = $this->makeLetter($admin);

        $this->actingAs($reviewer)
            ->post(route('incoming-letters.review.store', $letter), [
                'action' => 'archive_directly',
            ])
            ->assertRedirect(route('incoming-letters.show', $letter));

        foreach ($activeUsers as $recipient) {
            Notification::assertSentTo(
                $recipient,
                IncomingLetterArchivedDirectly::class,
                fn ($notification, array $channels): bool => $channels === ['database'],
            );
        }

        Notification::assertNotSentTo($inactiveUser, IncomingLetterArchivedDirectly::class);
        Notification::assertSentTimes(IncomingLetterArchivedDirectly::class, count($activeUsers));
        Notification::assertNotSentTo($activeUsers, IncomingLetterForwardedToDivision::class);
        Notification::assertNotSentTo($activeUsers, IncomingLetterSubmittedForReview::class);
        $this->assertSame(['database'], (new IncomingLetterArchivedDirectly($letter))->via($admin));
        $this->assertFalse(method_exists(IncomingLetterArchivedDirectly::class, 'toMail'));
    }

    public function test_forward_action_keeps_existing_email_and_in_app_recipients(): void
    {
        Notification::fake();

        $destination = $this->makeDivision('Keuangan', 'KEU');
        $otherDivision = $this->makeDivision('Redaksi', 'RED');
        $admin = $this->makeUser('admin_surat');
        $reviewer = $this->makeUser('pimpinan');
        $destinationHead = $this->makeUser('ketua_divisi', $destination);
        $otherHead = $this->makeUser('ketua_divisi', $otherDivision);
        $member = $this->makeUser('anggota_divisi', $destination);
        $inactiveUser = $this->makeUser('anggota_divisi', $destination, false);
        $activeUsers = [$admin, $reviewer, $destinationHead, $otherHead, $member];
        $letter = $this->makeLetter($admin);

        $this->actingAs($reviewer)
            ->post(route('incoming-letters.review.store', $letter), [
                'action' => 'forward',
                'destination_division_id' => $destination->id,
            ])
            ->assertRedirect(route('incoming-letters.show', $letter));

        Notification::assertSentTo(
            $destinationHead,
            IncomingLetterForwardedToDivision::class,
            fn ($notification, array $channels): bool => $channels === ['mail'],
        );
        Notification::assertNotSentTo(
            [$admin, $reviewer, $otherHead, $member, $inactiveUser],
            IncomingLetterForwardedToDivision::class,
            fn ($notification, array $channels): bool => in_array('mail', $channels, true),
        );

        foreach ($activeUsers as $recipient) {
            Notification::assertSentTo(
                $recipient,
                IncomingLetterForwardedToDivision::class,
                fn ($notification, array $channels): bool => $channels === ['database'],
            );
        }

        Notification::assertNotSentTo(
            $inactiveUser,
            IncomingLetterForwardedToDivision::class,
            fn ($notification, array $channels): bool => in_array('database', $channels, true),
        );
        Notification::assertNotSentTo($activeUsers, IncomingLetterArchivedDirectly::class);
        $letter->refresh();
        $this->assertSame($destination->id, $letter->destination_division_id);
        $this->assertSame(IncomingLetter::STATUS_SELESAI, $letter->status);
    }

    public function test_rolled_back_direct_archive_does_not_send_a_notification(): void
    {
        Notification::fake();

        $pimpinan = $this->makeUser('pimpinan');
        $letter = $this->makeLetter($pimpinan);
        IncomingLetterStatusHistory::creating(function (): void {
            throw new RuntimeException('Simulasi kegagalan history arsip langsung.');
        });
        $exception = null;

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($pimpinan)
                ->post(route('incoming-letters.review.store', $letter), [
                    'action' => 'archive_directly',
                ]);
        } catch (RuntimeException $caughtException) {
            $exception = $caughtException;
        } finally {
            IncomingLetterStatusHistory::flushEventListeners();
        }

        $this->assertNotNull($exception);
        $this->assertReviewDidNotChangeLetter($letter);
        $this->assertDatabaseCount('incoming_letter_status_histories', 0);
        Notification::assertNothingSent();
    }

    private function assertReviewDidNotChangeLetter(IncomingLetter $letter): void
    {
        $this->assertDatabaseMissing('incoming_letter_reviews', [
            'incoming_letter_id' => $letter->id,
        ]);
        $this->assertSame(IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN, $letter->fresh()->status);
        $this->assertNull($letter->destination_division_id);
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

    private function makeUser(
        string $roleSlug,
        ?Division $division = null,
        bool $isActive = true,
        ?string $name = null,
    ): User {
        $role = Role::query()->firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => Str::headline($roleSlug)],
        );

        return User::query()->create([
            'name' => $name ?? Str::headline($roleSlug).' '.Str::random(5),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'division_id' => $division?->id,
            'is_active' => $isActive,
        ]);
    }

    private function makeLetter(User $creator): IncomingLetter
    {
        return IncomingLetter::query()->create([
            'agenda_number' => 'AGD-'.fake()->unique()->numerify('####'),
            'letter_number' => '001/RS/VIII/2026',
            'sender_name' => 'Instansi Pengirim',
            'addressed_to' => 'Radar Surat',
            'letter_date' => '2026-08-18',
            'received_date' => '2026-08-19',
            'received_via' => 'email',
            'subject' => 'Permohonan pemeriksaan surat',
            'priority' => 'biasa',
            'destination_division_id' => null,
            'document_path' => 'incoming-letters/2026/private-document.pdf',
            'original_document_name' => 'surat.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => 1024,
            'status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'created_by' => $creator->id,
            'submitted_for_review_at' => now(),
        ]);
    }
}
