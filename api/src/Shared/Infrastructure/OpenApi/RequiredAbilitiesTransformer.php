<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure\OpenApi;

use Dedoc\Scramble\Contracts\OperationTransformer;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\RouteInfo;

final readonly class RequiredAbilitiesTransformer implements OperationTransformer
{
    /** Middleware that check Sanctum abilities: `ability` asks for any of them, `abilities` for all. */
    private const array PREFIXES = ['ability:', 'abilities:'];

    /** Copy the Sanctum abilities a route asks for to the OpenAPI operation (`x-abilities`). */
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        $abilities = [];

        foreach ($routeInfo->route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            foreach (self::PREFIXES as $prefix) {
                if (! str_starts_with($middleware, $prefix)) {
                    continue;
                }

                foreach (explode(',', substr($middleware, strlen($prefix))) as $ability) {
                    if ($ability !== '') {
                        $abilities[] = $ability;
                    }
                }
            }
        }

        if ($abilities !== []) {
            $operation->setExtensionProperty('abilities', array_values(array_unique($abilities)));
        }
    }
}
