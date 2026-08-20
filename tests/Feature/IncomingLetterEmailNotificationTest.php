<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\IncomingLetter;
use App\Models\Role;
use App\Models\User;
use App\Notifications\IncomingLetterForwardedToDivision;
use App\Notifications\IncomingLetterSubmittedForReview;
use App\Services\IncomingLetterNotificationService;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class IncomingLetterEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_submission_notifies_only_active_authorized_reviewers(): void
    {
        Notification::fake();

        $sdm = $this->makeDivision('Sumber Daya Manusia', 'SDM');
        $otherDivision = $this->makeDivision('Redaksi', 'RED');
        $admin = $this->makeUser('admin_surat', null, true, 'Admin Surat');
        $pimpinan = $this->makeUser('pimpinan', null, true, 'Pimpinan Aktif');
        $sdmHead = $this->makeUser('ketua_divisi', $sdm, true, 'Ketua SDM Aktif');
        $nonSdmHead = $this->makeUser('ketua_divisi', $otherDivision, true, 'Ketua Redaksi');
        $sdmMember = $this->makeUser('anggota_divisi', $sdm, true, 'Anggota SDM');
        $inactivePimpinan = $this->makeUser('pimpinan', null, false, 'Pimpinan Nonaktif');
        $inactiveSdmHead = $this->makeUser('ketua_divisi', $sdm, false, 'Ketua SDM Nonaktif');
        $letter = $this->makeLetter($admin);

        $this->actingAs($admin)
            ->patchJson(route('incoming-letters.submit-for-review', $letter))
            ->assertOk()
            ->assertJsonPath('status', IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN);

        Notification::assertSentTo(
            $pimpinan,
            IncomingLetterSubmittedForReview::class,
            fn ($notification, array $channels): bool => $channels === ['mail'],
        );
        Notification::assertSentTo(
            $sdmHead,
            IncomingLetterSubmittedForReview::class,
            fn ($notification, array $channels): bool => $channels === ['mail'],
        );
        Notification::assertNotSentTo(
            [$nonSdmHead, $sdmMember, $inactivePimpinan, $inactiveSdmHead, $admin],
            IncomingLetterSubmittedForReview::class,
            fn ($notification, array $channels): bool => in_array('mail', $channels, true),
        );

        $this->assertDatabaseHas('incoming_letters', [
            'id' => $letter->id,
            'status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
        ]);
        $this->assertDatabaseHas('incoming_letter_status_histories', [
            'incoming_letter_id' => $letter->id,
            'previous_status' => IncomingLetter::STATUS_BARU_DITERIMA,
            'new_status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
        ]);
    }

    public function test_completed_review_notifies_only_active_heads_of_the_destination_division(): void
    {
        Notification::fake();

        $destination = $this->makeDivision('Pemasaran', 'PEM');
        $otherDivision = $this->makeDivision('Redaksi', 'RED');
        $admin = $this->makeUser('admin_surat');
        $reviewer = $this->makeUser('pimpinan');
        $destinationHead = $this->makeUser('ketua_divisi', $destination, true, 'Ketua Pemasaran');
        $otherHead = $this->makeUser('ketua_divisi', $otherDivision, true, 'Ketua Redaksi');
        $destinationMember = $this->makeUser('anggota_divisi', $destination, true, 'Anggota Pemasaran');
        $inactiveDestinationHead = $this->makeUser('ketua_divisi', $destination, false, 'Ketua Pemasaran Nonaktif');
        $letter = $this->makeLetter($admin, IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN);

        $this->actingAs($reviewer)
            ->post(route('incoming-letters.review.store', $letter), [
                'destination_division_id' => $destination->id,
                'review_note' => 'Mohon ditindaklanjuti.',
            ])
            ->assertRedirect(route('incoming-letters.show', $letter));

        Notification::assertSentTo(
            $destinationHead,
            IncomingLetterForwardedToDivision::class,
            fn ($notification, array $channels): bool => $channels === ['mail'],
        );
        Notification::assertNotSentTo(
            [$otherHead, $destinationMember, $inactiveDestinationHead, $reviewer, $admin],
            IncomingLetterForwardedToDivision::class,
            fn ($notification, array $channels): bool => in_array('mail', $channels, true),
        );

        $letter->refresh();

        $this->assertSame(IncomingLetter::STATUS_SELESAI, $letter->status);
        $this->assertSame($destination->id, $letter->destination_division_id);
        $this->assertDatabaseHas('incoming_letter_reviews', [
            'incoming_letter_id' => $letter->id,
            'reviewed_by' => $reviewer->id,
            'destination_division_id' => $destination->id,
        ]);
        $this->assertDatabaseHas('incoming_letter_status_histories', [
            'incoming_letter_id' => $letter->id,
            'previous_status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            'new_status' => IncomingLetter::STATUS_SELESAI,
        ]);
    }

    public function test_mail_messages_use_the_show_route_and_never_attach_the_private_document(): void
    {
        $destination = $this->makeDivision('Pemasaran', 'PEM');
        $recipient = $this->makeUser('ketua_divisi', $destination, true, 'Ketua Pemasaran');
        $letter = $this->makeLetter($recipient, IncomingLetter::STATUS_SELESAI, $destination)
            ->load('destinationDivision:id,name');

        $submittedNotification = new IncomingLetterSubmittedForReview($letter);
        $submittedMail = $submittedNotification->toMail($recipient);
        $forwardedNotification = new IncomingLetterForwardedToDivision($letter);
        $forwardedMail = $forwardedNotification->toMail($recipient);

        $this->assertSame(['mail'], $submittedNotification->via($recipient));
        $this->assertSame('[SIRAPI] Surat Masuk Menunggu Pemeriksaan', $submittedMail->subject);
        $this->assertSame("Halo, {$recipient->name}.", $submittedMail->greeting);
        $this->assertContains('Pengirim: Instansi Pengirim', $submittedMail->introLines);
        $this->assertContains('Perihal: Undangan Rapat', $submittedMail->introLines);
        $this->assertContains('Prioritas: Segera', $submittedMail->introLines);
        $this->assertSame('Lihat Surat di SIRAPI', $submittedMail->actionText);
        $this->assertSame(route('incoming-letters.show', $letter), $submittedMail->actionUrl);
        $this->assertSame([], $submittedMail->attachments);
        $this->assertSame([], $submittedMail->rawAttachments);

        $this->assertSame(['mail'], $forwardedNotification->via($recipient));
        $this->assertSame('[SIRAPI] Surat Masuk untuk Divisi Anda', $forwardedMail->subject);
        $this->assertContains('Divisi tujuan: Pemasaran', $forwardedMail->introLines);
        $this->assertContains('Pengirim: Instansi Pengirim', $forwardedMail->introLines);
        $this->assertContains('Perihal: Undangan Rapat', $forwardedMail->introLines);
        $this->assertSame('Lihat Surat di SIRAPI', $forwardedMail->actionText);
        $this->assertSame(route('incoming-letters.show', $letter), $forwardedMail->actionUrl);
        $this->assertSame([], $forwardedMail->attachments);
        $this->assertSame([], $forwardedMail->rawAttachments);

        $mailPayload = json_encode([
            $submittedMail->introLines,
            $submittedMail->outroLines,
            $submittedMail->actionUrl,
            $forwardedMail->introLines,
            $forwardedMail->outroLines,
            $forwardedMail->actionUrl,
        ], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString($letter->document_path, $mailPayload);
    }

    public function test_rolled_back_submission_does_not_send_a_notification(): void
    {
        Notification::fake();

        $admin = $this->makeUser('admin_surat');
        $this->makeUser('pimpinan');
        $letter = $this->makeLetter($admin);
        $notificationService = app(IncomingLetterNotificationService::class);
        $exception = null;

        try {
            DB::transaction(function () use ($letter, $notificationService) {
                $letter->update(['status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN]);
                $notificationService->notifySubmittedForReviewAfterCommit($letter->id);

                throw new RuntimeException('Simulasi rollback setelah callback didaftarkan.');
            });
        } catch (RuntimeException $caughtException) {
            $exception = $caughtException;
        }

        $this->assertNotNull($exception);
        $this->assertSame(IncomingLetter::STATUS_BARU_DITERIMA, $letter->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_mail_delivery_failure_is_logged_without_rolling_back_the_committed_workflow(): void
    {
        $admin = $this->makeUser('admin_surat');
        $this->makeUser('pimpinan');
        $letter = $this->makeLetter($admin);
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('sendNow')
            ->once()
            ->with(
                Mockery::type(User::class),
                Mockery::type(IncomingLetterSubmittedForReview::class),
                ['mail'],
            )
            ->andThrow(new RuntimeException('Simulasi SMTP tidak tersedia.'));
        $dispatcher->shouldReceive('sendNow')
            ->once()
            ->with(
                Mockery::type(User::class),
                Mockery::type(IncomingLetterSubmittedForReview::class),
                ['database'],
            );
        $this->app->instance(Dispatcher::class, $dispatcher);
        Log::spy();

        $this->actingAs($admin)
            ->patchJson(route('incoming-letters.submit-for-review', $letter))
            ->assertOk()
            ->assertJsonPath('status', IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN);

        $this->assertSame(IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN, $letter->fresh()->status);
        $this->assertDatabaseHas('incoming_letter_status_histories', [
            'incoming_letter_id' => $letter->id,
            'new_status' => IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
        ]);
        Log::shouldHaveReceived('error')
            ->once()
            ->with(
                'Notifikasi Surat Masuk gagal dikirim.',
                Mockery::on(fn (array $context) => $context['incoming_letter_id'] === $letter->id
                    && $context['notification_event'] === 'submitted_for_review'
                    && $context['notification_channels'] === ['mail']
                    && $context['exception_class'] === RuntimeException::class),
            );
    }

    public function test_notification_side_effect_does_not_bypass_existing_authorization(): void
    {
        Notification::fake();

        $admin = $this->makeUser('admin_surat');
        $member = $this->makeUser('anggota_divisi');
        $this->makeUser('pimpinan');
        $letter = $this->makeLetter($admin);

        $this->actingAs($member)
            ->patch(route('incoming-letters.submit-for-review', $letter))
            ->assertForbidden();

        $this->assertSame(IncomingLetter::STATUS_BARU_DITERIMA, $letter->fresh()->status);
        Notification::assertNothingSent();
    }

    private function makeDivision(string $name, string $code): Division
    {
        return Division::query()->create([
            'name' => $name,
            'code' => $code,
            'is_active' => true,
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
            ['name' => str($roleSlug)->replace('_', ' ')->title()->toString()],
        );

        return User::query()->create([
            'name' => $name ?? fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role_id' => $role->id,
            'division_id' => $division?->id,
            'is_active' => $isActive,
        ]);
    }

    private function makeLetter(
        User $creator,
        string $status = IncomingLetter::STATUS_BARU_DITERIMA,
        ?Division $destination = null,
    ): IncomingLetter {
        return IncomingLetter::query()->create([
            'agenda_number' => 'AGD-'.fake()->unique()->numerify('####'),
            'letter_number' => '001/SIRAPI/VIII/2026',
            'sender_name' => 'Instansi Pengirim',
            'addressed_to' => 'Jawa Pos Radar Kediri',
            'letter_date' => '2026-08-18',
            'received_date' => '2026-08-19',
            'received_via' => 'email',
            'subject' => 'Undangan Rapat',
            'priority' => 'segera',
            'destination_division_id' => $destination?->id,
            'document_path' => 'incoming-letters/2026/private-document.pdf',
            'original_document_name' => 'surat.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => 1024,
            'status' => $status,
            'created_by' => $creator->id,
            'submitted_for_review_at' => $status === IncomingLetter::STATUS_BARU_DITERIMA ? null : now(),
        ]);
    }
}
