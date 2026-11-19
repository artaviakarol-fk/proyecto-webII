<?php

declare(strict_types=1);

namespace Src\Negocios\Profile\Infrastructure\Persistence\Repositories;

use Illuminate\Support\Str;
use Src\Negocios\Profile\Domain\Contracts\NegocioRepository;
use Src\Negocios\Profile\Domain\Entities\Negocio;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Src\Shared\Domain\ValueObjects\NegocioId;

final readonly class EloquentNegocioRepository implements NegocioRepository
{
    /** Persist the changes of a negocio; its name and state are managed elsewhere, so only the logo is written. */
    public function save(Negocio $negocio): void
    {
        NegocioModel::query()->whereKey($negocio->id->value)->update(['logo_path' => $negocio->logoPath]);
    }

    /** Answer a new time-ordered identifier. */
    public function nextId(): NegocioId
    {
        return new NegocioId((string) Str::uuid7());
    }

    /** Insert a new negocio with its name and state. */
    public function add(Negocio $negocio): void
    {
        NegocioModel::query()->create([
            'id' => $negocio->id->value,
            'nombre' => $negocio->name,
            'logo_path' => $negocio->logoPath,
            'estado' => $negocio->active ? NegocioModel::ACTIVE : NegocioModel::INACTIVE,
        ]);
    }

    /** Turn a negocio on or off. */
    public function setActive(NegocioId $id, bool $active): void
    {
        NegocioModel::query()->whereKey($id->value)->update(['estado' => $active ? NegocioModel::ACTIVE : NegocioModel::INACTIVE]);
    }

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
