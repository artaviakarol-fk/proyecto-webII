<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\Http\Controllers;

use Src\Shared\Application\UseCases\CheckHealth;
use Src\Shared\Infrastructure\Http\Resources\HealthResource;

final readonly class HealthController
{
    /** Report the status of the application and its database; always answers 200. */
    public function __invoke(CheckHealth $checkHealth): HealthResource
    {
        return new HealthResource(
            $checkHealth->execute(now()->toDateTimeImmutable()),
        );
    }
}
