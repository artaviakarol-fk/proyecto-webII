<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /** No test reaches a real service: a request that is not faked fails the test. */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }
}
