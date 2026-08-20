<?php

namespace App\Services;

use App\Models\Division;
use App\Models\IncomingLetter;
use App\Models\InternshipCertificate;
use App\Models\OutgoingLetter;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportQueryService
{
    /** @var array<int, string> */
    private const GLOBAL_ROLES = ['admin_surat', 'pimpinan'];

    /** @var array<int, string> */
    private const DIVISION_ROLES = ['ketua_divisi', 'anggota_divisi'];

    public function canAccess(User $user): bool
    {
        if (! $user->is_active) {
            return false;
        }

        $role = $user->role?->slug;

        return in_array($role, self::GLOBAL_ROLES, true)
            || (in_array($role, self::DIVISION_ROLES, true) && $user->division_id !== null);
    }

    public function hasGlobalScope(User $user): bool
    {
        return in_array($user->role?->slug, self::GLOBAL_ROLES, true);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<IncomingLetter>
     */
    public function incomingQuery(User $user, array $filters): Builder
    {
        $query = IncomingLetter::query();

        $this->applyDivisionScope($query, $user, 'incoming_letters.destination_division_id');
        $query->whereIn('incoming_letters.status', [
            IncomingLetter::STATUS_BARU_DITERIMA,
            IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN,
            IncomingLetter::STATUS_SELESAI,
        ]);

        $query
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('incoming_letters.agenda_number', 'like', "%{$search}%")
                        ->orWhere('incoming_letters.letter_number', 'like', "%{$search}%")
                        ->orWhere('incoming_letters.sender_name', 'like', "%{$search}%")
                        ->orWhere('incoming_letters.subject', 'like', "%{$search}%");
                });
            })
            ->when(
                $filters['start_date'] ?? null,
                fn (Builder $query, string $date) => $query->whereDate('incoming_letters.received_date', '>=', $date),
            )
            ->when(
                $filters['end_date'] ?? null,
                fn (Builder $query, string $date) => $query->whereDate('incoming_letters.received_date', '<=', $date),
            )
            ->when(
                $filters['status'] ?? null,
                fn (Builder $query, string $status) => $query->where('incoming_letters.status', $status),
            )
            ->when(
                $filters['priority'] ?? null,
                fn (Builder $query, string $priority) => $query->where('incoming_letters.priority', $priority),
            );

        if ($this->hasGlobalScope($user) && filled($filters['division_id'] ?? null)) {
            $query->where('incoming_letters.destination_division_id', (int) $filters['division_id']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<OutgoingLetter>
     */
    public function outgoingQuery(User $user, array $filters): Builder
    {
        $query = OutgoingLetter::query();

        $this->applyDivisionScope($query, $user, 'outgoing_letters.division_id');

        $query
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('outgoing_letters.reference_code', 'like', "%{$search}%")
                        ->orWhere('outgoing_letters.letter_number', 'like', "%{$search}%")
                        ->orWhere('outgoing_letters.recipient_name', 'like', "%{$search}%")
                        ->orWhere('outgoing_letters.subject', 'like', "%{$search}%");
                });
            })
            ->when(
                $filters['start_date'] ?? null,
                fn (Builder $query, string $date) => $query->whereDate('outgoing_letters.letter_date', '>=', $date),
            )
            ->when(
                $filters['end_date'] ?? null,
                fn (Builder $query, string $date) => $query->whereDate('outgoing_letters.letter_date', '<=', $date),
            );

        if ($this->hasGlobalScope($user) && filled($filters['division_id'] ?? null)) {
            $query->where('outgoing_letters.division_id', (int) $filters['division_id']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<InternshipCertificate>
     */
    public function certificateQuery(array $filters): Builder
    {
        return InternshipCertificate::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('internship_certificates.participant_name', 'like', "%{$search}%")
                        ->orWhere('internship_certificates.institution_name', 'like', "%{$search}%")
                        ->orWhere('internship_certificates.major_name', 'like', "%{$search}%");
                });
            })
            ->when(
                $filters['year'] ?? null,
                fn (Builder $query, int $year) => $query->whereYear('internship_certificates.end_date', $year),
            );
    }

    /**
     * @param  Builder<IncomingLetter>  $query
     * @return array{total: int, baru_diterima: int, menunggu_pemeriksaan: int, selesai: int}
     */
    public function incomingSummary(Builder $query): array
    {
        $summary = (clone $query)
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN incoming_letters.status = ? THEN 1 ELSE 0 END) as baru_diterima',
                [IncomingLetter::STATUS_BARU_DITERIMA],
            )
            ->selectRaw(
                'SUM(CASE WHEN incoming_letters.status = ? THEN 1 ELSE 0 END) as menunggu_pemeriksaan',
                [IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN],
            )
            ->selectRaw(
                'SUM(CASE WHEN incoming_letters.status = ? THEN 1 ELSE 0 END) as selesai',
                [IncomingLetter::STATUS_SELESAI],
            )
            ->first();

        return [
            'total' => (int) ($summary->total ?? 0),
            'baru_diterima' => (int) ($summary->baru_diterima ?? 0),
            'menunggu_pemeriksaan' => (int) ($summary->menunggu_pemeriksaan ?? 0),
            'selesai' => (int) ($summary->selesai ?? 0),
        ];
    }

    /**
     * @param  Builder<OutgoingLetter>  $query
     * @return array{total: int, division_count: int}
     */
    public function outgoingSummary(Builder $query): array
    {
        $summary = (clone $query)
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(DISTINCT outgoing_letters.division_id) as division_count')
            ->first();

        return [
            'total' => (int) ($summary->total ?? 0),
            'division_count' => (int) ($summary->division_count ?? 0),
        ];
    }

    /**
     * @param  Builder<InternshipCertificate>  $query
     * @return array{total: int}
     */
    public function certificateSummary(Builder $query): array
    {
        return ['total' => (clone $query)->count()];
    }

    /** @return Collection<int, int> */
    public function certificateYears(): Collection
    {
        $driver = InternshipCertificate::query()->getConnection()->getDriverName();
        $yearExpression = match ($driver) {
            'sqlite' => "CAST(strftime('%Y', end_date) AS INTEGER)",
            'pgsql' => 'EXTRACT(YEAR FROM end_date)',
            default => 'YEAR(end_date)',
        };

        return InternshipCertificate::query()
            ->selectRaw("{$yearExpression} AS certificate_year")
            ->distinct()
            ->orderByDesc('certificate_year')
            ->pluck('certificate_year')
            ->map(fn ($year): int => (int) $year);
    }

    /**
     * @param  Builder<IncomingLetter>  $query
     * @return Collection<int, object{division_id: ?int, division_name: ?string, total: int}>
     */
    public function incomingRecap(Builder $query): Collection
    {
        return (clone $query)
            ->toBase()
            ->leftJoin('divisions', 'divisions.id', '=', 'incoming_letters.destination_division_id')
            ->select([
                'incoming_letters.destination_division_id as division_id',
                'divisions.name as division_name',
            ])
            ->selectRaw('COUNT(*) as total')
            ->groupBy('incoming_letters.destination_division_id', 'divisions.name')
            ->orderByDesc('total')
            ->orderBy('divisions.name')
            ->get();
    }

    /**
     * @param  Builder<OutgoingLetter>  $query
     * @return Collection<int, object{division_id: int, division_name: ?string, total: int}>
     */
    public function outgoingRecap(Builder $query): Collection
    {
        return (clone $query)
            ->toBase()
            ->leftJoin('divisions', 'divisions.id', '=', 'outgoing_letters.division_id')
            ->select([
                'outgoing_letters.division_id',
                'divisions.name as division_name',
            ])
            ->selectRaw('COUNT(*) as total')
            ->groupBy('outgoing_letters.division_id', 'divisions.name')
            ->orderByDesc('total')
            ->orderBy('divisions.name')
            ->get();
    }

    /** @return Collection<int, Division> */
    public function activeDivisions(): Collection
    {
        return Division::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /** @param array<string, mixed> $filters */
    public function divisionLabel(User $user, array $filters): string
    {
        if (! $this->hasGlobalScope($user)) {
            return $user->division?->name ?? '-';
        }

        if (! filled($filters['division_id'] ?? null)) {
            return 'Semua Divisi';
        }

        return Division::query()->whereKey($filters['division_id'])->value('name') ?? '-';
    }

    /** @param array<string, mixed> $filters */
    public function periodLabel(array $filters): string
    {
        $start = $filters['start_date'] ?? null;
        $end = $filters['end_date'] ?? null;

        if ($start && $end) {
            return "{$start} s.d. {$end}";
        }

        if ($start) {
            return "Mulai {$start}";
        }

        if ($end) {
            return "Sampai {$end}";
        }

        return 'Semua Periode';
    }

    /**
     * @param  Builder<IncomingLetter>|Builder<OutgoingLetter>  $query
     */
    private function applyDivisionScope(Builder $query, User $user, string $column): void
    {
        if ($this->hasGlobalScope($user)) {
            return;
        }

        if (in_array($user->role?->slug, self::DIVISION_ROLES, true) && $user->division_id !== null) {
            $query->where($column, $user->division_id);

            return;
        }

        $query->whereRaw('1 = 0');
    }
}
