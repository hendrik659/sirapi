<?php

namespace App\Http\Controllers;

use App\Models\Division;
use App\Models\IncomingLetter;
use App\Models\IncomingLetterStatusHistory;
use App\Models\OutgoingLetter;
use App\Models\OutgoingLetterHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /** @var array<int, string> */
    private const INDONESIAN_MONTH_ABBREVIATIONS = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'Mei',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Agu',
        9 => 'Sep',
        10 => 'Okt',
        11 => 'Nov',
        12 => 'Des',
    ];

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing('role');

        if ($user->role?->slug !== 'admin_surat') {
            return view('dashboard.index', ['isAdminDashboard' => false]);
        }

        return view('dashboard.index', array_merge(
            ['isAdminDashboard' => true],
            $this->adminDashboardData(),
        ));
    }

    /** @return array<string, mixed> */
    private function adminDashboardData(): array
    {
        $incomingCounts = IncomingLetter::query()
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as baru_diterima',
                [IncomingLetter::STATUS_BARU_DITERIMA],
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as menunggu_pemeriksaan',
                [IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN],
            )
            ->first();
        $userCounts = User::query()
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN is_active = ? THEN 1 ELSE 0 END) as active', [true])
            ->selectRaw('SUM(CASE WHEN is_active = ? THEN 1 ELSE 0 END) as inactive', [false])
            ->first();

        $months = collect(range(5, 0))
            ->map(fn (int $monthsAgo): CarbonImmutable => CarbonImmutable::now()
                ->startOfMonth()
                ->subMonths($monthsAgo));

        return [
            'totalIncomingLetters' => (int) ($incomingCounts->total ?? 0),
            'newIncomingLetters' => (int) ($incomingCounts->baru_diterima ?? 0),
            'waitingReviewIncomingLetters' => (int) ($incomingCounts->menunggu_pemeriksaan ?? 0),
            'totalOutgoingLetters' => OutgoingLetter::query()->count(),
            'recentIncomingLetters' => IncomingLetter::query()
                ->select(['id', 'subject', 'sender_name', 'received_date', 'status'])
                ->orderByDesc('received_date')
                ->latest('id')
                ->limit(5)
                ->get(),
            'recentOutgoingLetters' => OutgoingLetter::query()
                ->select(['id', 'reference_code', 'subject', 'recipient_name', 'letter_date'])
                ->orderByDesc('letter_date')
                ->latest('id')
                ->limit(5)
                ->get(),
            'sixMonthLabels' => $months
                ->map(fn (CarbonImmutable $month): string => sprintf(
                    "%s '%s",
                    self::INDONESIAN_MONTH_ABBREVIATIONS[$month->month],
                    $month->format('y'),
                ))
                ->values()
                ->all(),
            'sixMonthIncomingTrend' => $this->monthlyCounts(
                IncomingLetter::query(),
                'received_date',
                $months,
            ),
            'sixMonthOutgoingTrend' => $this->monthlyCounts(
                OutgoingLetter::query(),
                'letter_date',
                $months,
            ),
            'activeUsers' => (int) ($userCounts->active ?? 0),
            'inactiveUsers' => (int) ($userCounts->inactive ?? 0),
            'totalUsers' => (int) ($userCounts->total ?? 0),
            'activeDivisions' => Division::query()->where('is_active', true)->count(),
            'recentActivities' => $this->recentActivities(),
            'todayLabel' => CarbonImmutable::now()->locale('id')->translatedFormat('l, j F Y'),
        ];
    }

    /**
     * @param  Collection<int, CarbonImmutable>  $months
     * @return array<int, int>
     */
    private function monthlyCounts(Builder $query, string $dateColumn, Collection $months): array
    {
        $monthExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$dateColumn})",
            'pgsql' => "to_char({$dateColumn}, 'YYYY-MM')",
            'sqlsrv' => "FORMAT({$dateColumn}, 'yyyy-MM')",
            default => "DATE_FORMAT({$dateColumn}, '%Y-%m')",
        };
        $startDate = $months->first()->startOfMonth();
        $endDate = $months->last()->addMonth()->startOfMonth();
        $counts = $query
            ->where($dateColumn, '>=', $startDate->toDateString())
            ->where($dateColumn, '<', $endDate->toDateString())
            ->selectRaw("{$monthExpression} as month_key, COUNT(*) as total")
            ->groupByRaw($monthExpression)
            ->pluck('total', 'month_key');

        return $months
            ->map(fn (CarbonImmutable $month): int => (int) ($counts[$month->format('Y-m')] ?? 0))
            ->values()
            ->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function recentActivities(): Collection
    {
        $incomingActivities = IncomingLetterStatusHistory::query()
            ->with([
                'incomingLetter:id,agenda_number,subject',
                'changedBy:id,name',
            ])
            ->latest('created_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (IncomingLetterStatusHistory $history): array => [
                'id' => $history->id,
                'source' => 'incoming',
                'activity' => $history->activity,
                'reference' => $history->incomingLetter?->agenda_number ?? '-',
                'subject' => $history->incomingLetter?->subject ?? '-',
                'actor' => $history->changedBy?->name ?? '-',
                'created_at' => $history->created_at,
            ]);

        $outgoingActivities = OutgoingLetterHistory::query()
            ->with([
                'outgoingLetter:id,reference_code,subject',
                'changedBy:id,name',
            ])
            ->latest('created_at')
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (OutgoingLetterHistory $history): array => [
                'id' => $history->id,
                'source' => 'outgoing',
                'activity' => $history->activity,
                'reference' => $history->outgoingLetter?->reference_code ?? '-',
                'subject' => $history->outgoingLetter?->subject ?? '-',
                'actor' => $history->changedBy?->name ?? '-',
                'created_at' => $history->created_at,
            ]);

        return $incomingActivities
            ->concat($outgoingActivities)
            ->sort(function (array $left, array $right): int {
                $byDate = $right['created_at']->getTimestamp() <=> $left['created_at']->getTimestamp();

                return $byDate !== 0 ? $byDate : $right['id'] <=> $left['id'];
            })
            ->take(5)
            ->values();
    }
}
