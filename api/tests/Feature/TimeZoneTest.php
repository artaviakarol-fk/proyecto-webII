<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TimeZoneTest extends TestCase
{
    private const string TIMEZONE = 'America/Costa_Rica';

    /** The application clock runs on Costa Rica time. */
    public function test_application_uses_costa_rica_time(): void
    {
        $this->assertSame(self::TIMEZONE, config('app.timezone'));
        $this->assertSame(self::TIMEZONE, date_default_timezone_get());
        $this->assertSame(self::TIMEZONE, now()->timezoneName);
    }

    /** The database session interprets timestamps in Costa Rica time. */
    public function test_database_session_uses_costa_rica_time(): void
    {
        $this->assertSame(self::TIMEZONE, DB::scalar('show timezone'));
    }
}
