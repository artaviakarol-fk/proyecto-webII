<?php

namespace App\Infrastructure\Http\Controllers;

use App\Application\Health\CheckHealth;
use App\Infrastructure\Http\Problems\ProblemDetails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class HealthController
{
    public function __invoke(Request $request, CheckHealth $checkHealth): JsonResponse
    {
        $report = $checkHealth();

        if (! $report->isHealthy()) {
            return ProblemDetails::response(
                503,
                'Servicio no disponible.',
                'La base de datos no responde.',
                '/'.$request->path(),
                ['checks' => $report->toArray()['checks']],
            );
        }

        return new JsonResponse($report->toArray());
    }
}
