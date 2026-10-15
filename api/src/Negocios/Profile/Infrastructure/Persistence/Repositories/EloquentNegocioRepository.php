<?php

declare(strict_types=1);

namespace Src\Negocios\Profile\Infrastructure\Persistence\Repositories;

use Src\Negocios\Profile\Domain\Contracts\NegocioRepository;
use Src\Negocios\Profile\Domain\Entities\Negocio;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class EloquentNegocioRepository implements NegocioRepository
{
    /** Find a negocio by its identifier. */
    public function find(NegocioId $id): ?Negocio
    {
        $model = NegocioModel::query()->find($id->value);

        return $model === null ? null : Negocio::reconstitute(
            new NegocioId($model->id),
            $model->nombre,
            $model->logo_path,
            $model->estado === NegocioModel::ACTIVE,
        );
    }
}
