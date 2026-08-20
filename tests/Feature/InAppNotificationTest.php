<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\IncomingLetter;
use App\Models\OutgoingLetter;
use App\Models\Role;
use App\Models\User;
use App\Notifications\IncomingLetterForwardedToDivision;
use App\Notifications\IncomingLetterSubmittedForReview;
use App\Notifications\OutgoingLetterCreated;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class InAppNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_submission_stores_database_notifications_only_for_active_reviewers(): void
    {
        $sdm = $this->makeDivision('Sumber Daya Manusia', 'SDM');
        $otherDivision = $this->makeDivision('Redaksi', 'RED');
        $admin = $this->makeUser('admin_surat', null, true, 'Admin Surat');
        $pimpinan = $this->makeUser('pimpinan', null, true, 'Pimpinan Aktif');
        $sdmHead = $this->makeUser('ketua_divisi', $sdm, true, 'Ketua SDM Aktif');
        $excludedUsers = [
            $admin,
            $this->makeUser('ketua_divisi', $otherDivision, true, 'Ketua Redaksi'),
            $this->makeUser('anggota_divisi', $sdm, true, 'Anggota SDM'),
            $this->makeUser('pimpinan', null, false, 'Pimpinan Nonaktif'),
            $this->makeUser('ketua_divisi', $sdm, false, 'Ketua SDM Nonaktif'),
        ];
        $letter = $this->makeIncomingLetter($admin);

        $this->actingAs($admin)
            ->patchJson(route('incoming-letters.submit-for-review', $letter))
            ->assertOk();

        foreach ([$pimpinan, $sdmHead] as $recipient) {
            $this->assertSame(1, $recipient->notifications()->count());
        }

        foreach ($excludedUsers as $excludedUser) {
            $this->assertSame(0, $excludedUser->notifications()->count());
        }

        $payload = $pimpinan->notifications()->firstOrFail()->data;
        $this->assertSame('incoming_letter_submitted_for_review', $payload['kind']);
        $this->assertSame('Surat Masuk Menunggu Pemeriksaan', $payload['title']);
        $this->assertSame($letter->id, $payload['incoming_letter_id']);
        $this->assertStringContainsString('Instansi Pengirim', $payload['message']);
        $this->assertStringContainsString('Undangan Rapat', $payload['message']);
        $this->assertArrayHasKey('icon', $payload);
        $this->assertStringNotContainsString('document_path', json_encode($payload, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($letter->document_path, json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_completed_review_stores_database_notification_for_every_active_user(): void
    {
        $destination = $this->makeDivision('Keuangan', 'KEU');
        $otherDivision = $this->makeDivision('Redaksi', 'RED');
        $admin = $this->makeUser('admin_surat', null, true, 'Admin Surat');
        $reviewer = $this->makeUser('pimpinan', null, true, 'Pimpinan Pemeriksa');
        $destinationHead = $this->makeUser('ketua_divisi', $destination, true, 'Ketua Keuangan');
        $otherHead = $this->makeUser('ketua_divisi', $otherDivision, true, 'Ketua Redaksi');
        $destinationMember = $this->makeUser('anggota_divisi', $destination, true, 'Anggota Keuangan');
        $inactiveUser = $this->makeUser('anggota_divisi', $destination, false, 'Anggota Nonaktif');
        $letter = $this->makeIncomingLetter($admin, IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN);

        $this->actingAs($reviewer)
            ->post(route('incoming-letters.review.store', $letter), [
                'destination_division_id' => $destination->id,
                'review_note' => null,
            ])
            ->assertRedirect(route('incoming-letters.show', $letter));

        foreach ([$admin, $reviewer, $destinationHead, $otherHead, $destinationMember] as $recipient) {
            $this->assertSame(1, $recipient->notifications()->count(), "Notifikasi tidak tersimpan untuk {$recipient->name}.");
        }

        $this->assertSame(0, $inactiveUser->notifications()->count());

        $payload = $admin->notifications()->firstOrFail()->data;
        $this->assertSame('incoming_letter_forwarded', $payload['kind']);
        $this->assertSame('Surat Masuk Diteruskan ke Divisi Keuangan', $payload['title']);
        $this->assertSame($letter->id, $payload['incoming_letter_id']);
        $this->assertSame($destination->id, $payload['destination_division_id']);
        $this->assertSame($reviewer->id, $payload['reviewer_id']);
        $this->assertStringContainsString($reviewer->name, $payload['message']);
        $this->assertStringContainsString($destination->name, $payload['message']);
        $this->assertStringNotContainsString('document_path', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_completed_review_keeps_email_limited_to_active_destination_heads(): void
    {
        Notification::fake();

        $destination = $this->makeDivision('Keuangan', 'KEU');
        $otherDivision = $this->makeDivision('Redaksi', 'RED');
        $admin = $this->makeUser('admin_surat');
        $reviewer = $this->makeUser('pimpinan');
        $destinationHead = $this->makeUser('ketua_divisi', $destination);
        $otherHead = $this->makeUser('ketua_divisi', $otherDivision);
        $member = $this->makeUser('anggota_divisi', $destination);
        $inactiveHead = $this->makeUser('ketua_divisi', $destination, false);
        $letter = $this->makeIncomingLetter($admin, IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN);

        $this->actingAs($reviewer)
            ->post(route('incoming-letters.review.store', $letter), [
                'destination_division_id' => $destination->id,
                'review_note' => null,
            ])
            ->assertRedirect(route('incoming-letters.show', $letter));

        Notification::assertSentTo(
            $destinationHead,
            IncomingLetterForwardedToDivision::class,
            fn ($notification, array $channels): bool => $channels === ['mail'],
        );
        Notification::assertNotSentTo(
            [$admin, $reviewer, $otherHead, $member, $inactiveHead],
            IncomingLetterForwardedToDivision::class,
            fn ($notification, array $channels): bool => in_array('mail', $channels, true),
        );

        foreach ([$admin, $reviewer, $destinationHead, $otherHead, $member] as $recipient) {
            Notification::assertSentTo(
                $recipient,
                IncomingLetterForwardedToDivision::class,
                fn ($notification, array $channels): bool => $channels === ['database'],
            );
        }
    }

    public function test_outgoing_store_notifies_all_active_users_through_database_only(): void
    {
        $division = $this->makeDivision('Redaksi', 'RED');
        $creator = $this->makeUser('anggota_divisi', $division, true, 'Budi Santoso');
        $activeUsers = [
            $creator,
            $this->makeUser('admin_surat', null, true, 'Admin Surat'),
            $this->makeUser('pimpinan', null, true, 'Pimpinan'),
            $this->makeUser('ketua_divisi', $division, true, 'Ketua Redaksi'),
        ];
        $inactiveUser = $this->makeUser('anggota_divisi', $division, false, 'Anggota Nonaktif');

        $this->actingAs($creator)
            ->postJson(route('outgoing-letters.store'), $this->outgoingPayload())
            ->assertCreated();

        $outgoingLetter = OutgoingLetter::query()->firstOrFail()->load(['division', 'creator']);

        foreach ($activeUsers as $recipient) {
            $this->assertSame(1, $recipient->notifications()->count(), "Notifikasi tidak tersimpan untuk {$recipient->name}.");
        }

        $this->assertSame(0, $inactiveUser->notifications()->count());

        $notification = new OutgoingLetterCreated($outgoingLetter);
        $payload = $creator->notifications()->firstOrFail()->data;
        $this->assertSame(['database'], $notification->via($creator));
        $this->assertSame('outgoing_letter_created', $payload['kind']);
        $this->assertSame('Surat Keluar Baru Diarsipkan', $payload['title']);
        $this->assertSame($outgoingLetter->id, $payload['outgoing_letter_id']);
        $this->assertSame($creator->id, $payload['created_by']);
        $this->assertSame($division->id, $payload['division_id']);
        $this->assertStringContainsString('013/RK/VIII/2026', $payload['message']);
        $this->assertStringContainsString($division->name, $payload['message']);
        $this->assertStringContainsString($creator->name, $payload['message']);
        $this->assertStringNotContainsString('document_path', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function test_outgoing_notification_failure_does_not_rollback_letter_or_history(): void
    {
        $division = $this->makeDivision();
        $creator = $this->makeUser('anggota_divisi', $division);
        $dispatcher = Mockery::mock(Dispatcher::class);
        $dispatcher->shouldReceive('sendNow')
            ->once()
            ->with(
                Mockery::type(User::class),
                Mockery::type(OutgoingLetterCreated::class),
                ['database'],
            )
            ->andThrow(new RuntimeException('Simulasi database notification gagal.'));
        $this->app->instance(Dispatcher::class, $dispatcher);
        Log::spy();

        $this->actingAs($creator)
            ->postJson(route('outgoing-letters.store'), $this->outgoingPayload())
            ->assertCreated();

        $outgoingLetter = OutgoingLetter::query()->firstOrFail();
        $this->assertDatabaseHas('outgoing_letter_histories', [
            'outgoing_letter_id' => $outgoingLetter->id,
            'activity' => 'Surat Keluar dicatat',
        ]);
        Log::shouldHaveReceived('error')
            ->once()
            ->with(
                'Notifikasi Surat Keluar gagal dikirim.',
                Mockery::on(fn (array $context): bool => $context['outgoing_letter_id'] === $outgoingLetter->id
                    && $context['notification_channels'] === ['database']
                    && $context['exception_class'] === RuntimeException::class),
            );
    }

    public function test_notification_routes_require_authentication_and_enforce_ownership(): void
    {
        $division = $this->makeDivision();
        $owner = $this->makeUser('admin_surat', null, true, 'Pemilik Notifikasi');
        $otherUser = $this->makeUser('pimpinan', null, true, 'Pengguna Lain');
        $letter = $this->makeIncomingLetter($owner);
        $outgoingLetter = $this->makeOutgoingLetter($otherUser, $division);
        $ownerNotification = $this->storeNotification($owner, [
            'kind' => 'incoming_letter_submitted_for_review',
            'title' => 'Notifikasi Milik A',
            'message' => 'Pesan hanya untuk Pemilik Notifikasi.',
            'incoming_letter_id' => $letter->id,
            'icon' => 'fa-solid fa-envelope-open-text',
        ]);
        $otherNotification = $this->storeNotification($otherUser, [
            'kind' => 'outgoing_letter_created',
            'title' => 'Notifikasi Milik B',
            'message' => 'Pesan hanya untuk Pengguna Lain.',
            'outgoing_letter_id' => $outgoingLetter->id,
            'icon' => 'fa-solid fa-paper-plane',
        ]);

        $this->get(route('notifications.index'))->assertRedirect(route('login'));
        $this->patch(route('notifications.open', $ownerNotification->id))->assertRedirect(route('login'));
        $this->patch(route('notifications.read-all'))->assertRedirect(route('login'));

        $this->actingAs($owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Notifikasi Milik A')
            ->assertDontSee('Notifikasi Milik B');

        $this->actingAs($owner)
            ->patch(route('notifications.open', $otherNotification->id))
            ->assertNotFound();
        $this->assertNull($otherNotification->fresh()->read_at);

        $this->actingAs($owner)
            ->patch(route('notifications.open', $ownerNotification->id))
            ->assertRedirect(route('incoming-letters.show', $letter));
        $this->assertNotNull($ownerNotification->fresh()->read_at);
    }

    public function test_open_routes_outgoing_notification_to_existing_show_page(): void
    {
        $division = $this->makeDivision();
        $user = $this->makeUser('anggota_divisi', $division);
        $outgoingLetter = $this->makeOutgoingLetter($user, $division);
        $notification = $this->storeNotification($user, [
            'kind' => 'outgoing_letter_created',
            'title' => 'Surat Keluar Baru Diarsipkan',
            'message' => 'Surat Keluar baru tersedia.',
            'outgoing_letter_id' => $outgoingLetter->id,
            'icon' => 'fa-solid fa-paper-plane',
        ]);

        $this->actingAs($user)
            ->patch(route('notifications.open', $notification->id))
            ->assertRedirect(route('outgoing-letters.show', $outgoingLetter));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_read_all_marks_only_the_authenticated_users_notifications(): void
    {
        $owner = $this->makeUser('admin_surat');
        $otherUser = $this->makeUser('pimpinan');
        $ownerUnread = $this->storeNotification($owner, $this->genericPayload('Belum Dibaca A'));
        $ownerRead = $this->storeNotification($owner, $this->genericPayload('Sudah Dibaca A'), now());
        $otherUnread = $this->storeNotification($otherUser, $this->genericPayload('Belum Dibaca B'));

        $this->actingAs($owner)
            ->patch(route('notifications.read-all'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Semua notifikasi telah ditandai sebagai dibaca.');

        $this->assertNotNull($ownerUnread->fresh()->read_at);
        $this->assertNotNull($ownerRead->fresh()->read_at);
        $this->assertNull($otherUnread->fresh()->read_at);
    }

    public function test_index_filters_paginates_and_global_bell_shows_five_latest_notifications(): void
    {
        $user = $this->makeUser('admin_surat');

        foreach (range(1, 16) as $number) {
            $this->storeNotification(
                $user,
                $this->genericPayload("Notifikasi {$number}"),
                $number <= 8 ? now() : null,
                now()->addSeconds($number),
            );
        }

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertViewHas('notifications', fn (LengthAwarePaginator $notifications): bool => $notifications->perPage() === 15
                && $notifications->total() === 16)
            ->assertSee('Tandai Semua Dibaca')
            ->assertSee('Belum Dibaca');

        $this->actingAs($user)
            ->get(route('notifications.index', ['filter' => 'unread']))
            ->assertOk()
            ->assertViewHas('notifications', fn (LengthAwarePaginator $notifications): bool => $notifications->total() === 8)
            ->assertSee('aria-current="page"', false);

        $dashboard = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-testid="notification-bell"', false)
            ->assertSee('data-testid="notification-unread-badge"', false)
            ->assertSee('Notifikasi 16')
            ->assertSee('Notifikasi 12')
            ->assertDontSee('Notifikasi 11');

        $this->assertSame(5, substr_count($dashboard->getContent(), 'data-testid="notification-dropdown-item"'));

        $emptyUser = $this->makeUser('pimpinan');
        $this->actingAs($emptyUser)
            ->get(route('notifications.index', ['filter' => 'unread']))
            ->assertOk()
            ->assertSee('Tidak ada notifikasi yang belum dibaca.');
    }

    private function makeDivision(string $name = 'Redaksi', string $code = 'RED'): Division
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

    private function makeIncomingLetter(
        User $creator,
        string $status = IncomingLetter::STATUS_BARU_DITERIMA,
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
            'document_path' => 'incoming-letters/2026/private-document.pdf',
            'original_document_name' => 'surat.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => 1024,
            'status' => $status,
            'created_by' => $creator->id,
            'submitted_for_review_at' => $status === IncomingLetter::STATUS_BARU_DITERIMA ? null : now(),
        ]);
    }

    private function makeOutgoingLetter(User $creator, Division $division): OutgoingLetter
    {
        return OutgoingLetter::query()->create([
            'reference_code' => 'SK-2026-999',
            'letter_number' => '999/RK/VIII/2026',
            'letter_date' => '2026-08-19',
            'recipient_name' => 'Penerima Surat',
            'recipient_address' => null,
            'subject' => 'Surat Keluar Final',
            'division_id' => $division->id,
            'created_by' => $creator->id,
            'document_path' => 'outgoing-letters/2026/private-document.pdf',
            'original_document_name' => 'surat-keluar.pdf',
            'document_mime_type' => 'application/pdf',
            'document_size' => 1024,
        ]);
    }

    /**
     * @param  array<string, int|string>  $data
     */
    private function storeNotification(
        User $user,
        array $data,
        mixed $readAt = null,
        mixed $createdAt = null,
    ): DatabaseNotification {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => IncomingLetterSubmittedForReview::class,
            'data' => $data,
            'read_at' => $readAt,
            'created_at' => $createdAt ?? now(),
            'updated_at' => $createdAt ?? now(),
        ]);

        return $notification;
    }

    /**
     * @return array<string, int|string>
     */
    private function genericPayload(string $title): array
    {
        return [
            'kind' => 'incoming_letter_submitted_for_review',
            'title' => $title,
            'message' => "Pesan {$title}",
            'incoming_letter_id' => 999,
            'icon' => 'fa-regular fa-bell',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function outgoingPayload(): array
    {
        return [
            'letter_number' => '013/RK/VIII/2026',
            'letter_date' => '2026-08-19',
            'recipient_name' => 'Penerima Surat',
            'recipient_address' => 'Jalan Penerima Nomor 1',
            'subject' => 'Surat Keluar Final',
            'document' => UploadedFile::fake()->create('surat-final.pdf', 100, 'application/pdf'),
        ];
    }
}
