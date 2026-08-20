<?php

namespace App\Support;

use App\Models\IncomingLetter;

final class IncomingLetterStatusPresenter
{
    /**
     * @var array<string, array{label: string, variant: string}>
     */
    private const STATUSES = [
        IncomingLetter::STATUS_BARU_DITERIMA => [
            'label' => 'Baru Diterima',
            'variant' => 'new',
        ],
        IncomingLetter::STATUS_MENUNGGU_PEMERIKSAAN => [
            'label' => 'Menunggu Pemeriksaan',
            'variant' => 'waiting',
        ],
        IncomingLetter::STATUS_SELESAI => [
            'label' => 'Selesai',
            'variant' => 'done',
        ],
    ];

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_map(
            static fn (array $status): string => $status['label'],
            self::STATUSES,
        );
    }

    public static function label(?string $status): string
    {
        return self::STATUSES[$status]['label'] ?? ($status ?: '-');
    }

    public static function variant(?string $status): string
    {
        return self::STATUSES[$status]['variant'] ?? 'unknown';
    }
}
