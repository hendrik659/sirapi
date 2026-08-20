<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class DateTimeFormatter
{
    public static function human(?CarbonInterface $dateTime): string
    {
        if ($dateTime === null) {
            return '-';
        }

        return $dateTime
            ->copy()
            ->setTimezone((string) config('app.timezone'))
            ->locale('id')
            ->translatedFormat('j F Y, H:i').' WIB';
    }
}
