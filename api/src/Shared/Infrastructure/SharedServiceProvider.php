<?php

declare(strict_types=1);

namespace Src\Shared\Infrastructure;

use Dedoc\Scramble\Scramble;
use Illuminate\Support\ServiceProvider;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\Contracts\DatabaseStatusChecker;
use Src\Shared\Domain\Contracts\ImageStorage;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\Contracts\TransactionRunner;
use Src\Shared\Infrastructure\OpenApi\ProblemResponsesTransformer;
use Src\Shared\Infrastructure\OpenApi\RelativeServerTransformer;
use Src\Shared\Infrastructure\OpenApi\RequiredAbilitiesTransformer;
use Src\Shared\Infrastructure\Persistence\DatabaseConnectionStatusChecker;
use Src\Shared\Infrastructure\Persistence\DatabaseTransactionRunner;
use Src\Shared\Infrastructure\Storage\DiskImageStorage;
use Src\Shared\Infrastructure\Tenancy\AuthenticatedCurrentUser;
use Src\Shared\Infrastructure\Tenancy\AuthenticatedNegocioContext;

final class SharedServiceProvider extends ServiceProvider
{
    /**
     * Shared ports bound to their adapters.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        DatabaseStatusChecker::class => DatabaseConnectionStatusChecker::class,
        NegocioContext::class => AuthenticatedNegocioContext::class,
        ImageStorage::class => DiskImageStorage::class,
        CurrentUser::class => AuthenticatedCurrentUser::class,
        TransactionRunner::class => DatabaseTransactionRunner::class,
    ];

    /** Load the rules of the private channels and adjust the generated API contract. */
    public function boot(): void
    {
        // Las reglas de los canales privados de Reverb. La ruta que firma el permiso es POST /api/v1/broadcasting/auth,
        // con el token de Sanctum, y está en Http/routes.php para que quede en la documentación.
        require base_path('routes/channels.php');

        Scramble::configure()
            ->withOperationTransformers([
                RequiredAbilitiesTransformer::class,
            ])
            ->withDocumentTransformers([
                ProblemResponsesTransformer::class,
                RelativeServerTransformer::class,
            ]);
    }
}
