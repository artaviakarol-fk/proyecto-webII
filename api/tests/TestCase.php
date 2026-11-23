<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Src\Inventory\Reservations\Infrastructure\Broadcasting\ReservaCancelada;
use Src\Inventory\Reservations\Infrastructure\Broadcasting\ReservaCreada;

abstract class TestCase extends BaseTestCase
{
    /** No test reaches a real service: a request that is not faked fails the test, and Reverb is never called. */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        // With the sync queue a broadcast is sent at once and would try to reach a Reverb that is not running.
        // The tests of the agenda assert on these faked events instead.
        Event::fake([ReservaCreada::class, ReservaCancelada::class]);
    }
}
