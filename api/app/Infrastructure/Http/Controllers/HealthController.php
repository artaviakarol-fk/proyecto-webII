<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Health\CheckHealth;
use Illuminate\Http\JsonResponse;

final class HealthController
{
    public function __invoke(CheckHealth $checkHealth): JsonResponse
    {
        return new JsonResponse($checkHealth()->toArray());
    }
}
