<?php

namespace Tests\Feature;

use App\Support\DateTimeFormatter;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TimezonePresentationTest extends TestCase
{
    public function test_application_uses_asia_jakarta_as_its_single_timezone_source(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', now()->getTimezone()->getName());
        $this->assertSame(420, now()->utcOffset());
    }

    public function test_datetime_presenter_converts_to_indonesian_western_time(): void
    {
        $utcTime = CarbonImmutable::parse('2026-08-17 10:10:00', 'UTC');

        $this->assertSame(
            '17 Agustus 2026, 17:10 WIB',
            DateTimeFormatter::human($utcTime),
        );
    }
}
