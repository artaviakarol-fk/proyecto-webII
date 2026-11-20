<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Src\Platform\Tenants\Application\Contracts\TenantDirectory;
use Src\Platform\Tenants\Application\DTOs\TenantAdministrator;
use Src\Platform\Tenants\Application\DTOs\TenantSummary;
use Src\Shared\Domain\ValueObjects\NegocioId;
use Src\Shared\Domain\ValueObjects\Page;
use Src\Shared\Domain\ValueObjects\PageRequest;
use stdClass;

/**
 * Reads the negocios of the platform and their administrators. It only reads the tables of the platform (negocios and
 * the accounts), which have no row-level security: the tables with the data of each negocio are never touched here.
 */
final readonly class DatabaseTenantDirectory implements TenantDirectory
{
    /**
     * One page of the negocios, the newest first, with the administrators of the whole page in one more query.
     *
     * @return Page<TenantSummary>
     */
    public function list(?string $term, PageRequest $page): Page
    {
        $query = DB::table('negocios')
            ->when($term !== null && $term !== '', function (Builder $query) use ($term): void {
                $query->where('nombre', 'ilike', '%'.addcslashes((string) $term, '%_\\').'%');
            });

        $total = $query->getCountForPagination();
        $rows = $total > $page->offset()
            ? array_values($query->orderByDesc('created_at')->orderByDesc('id')->forPage($page->number, $page->size)->get(['id', 'nombre', 'estado', 'created_at'])->all())
            : [];

        $administrators = $this->administratorsOf(array_map(fn (stdClass $row): string => (string) $row->id, $rows));

        return new Page(
            array_map(fn (stdClass $row): TenantSummary => $this->summary($row, $administrators[(string) $row->id] ?? []), $rows),
            $total,
            $page,
        );
    }

    /** One negocio with its administrators. */
    public function find(NegocioId $id): ?TenantSummary
    {
        $row = DB::table('negocios')->where('id', $id->value)->first(['id', 'nombre', 'estado', 'created_at']);

        return $row === null ? null : $this->summary($row, $this->administratorsOf([$id->value])[$id->value] ?? []);
    }

    /**
     * The administrators of some negocios, by negocio.
     *
     * @param  list<string>  $negocioIds
     * @return array<string, list<TenantAdministrator>>
     */
    private function administratorsOf(array $negocioIds): array
    {
        if ($negocioIds === []) {
            return [];
        }

        $byNegocio = [];
        $rows = DB::table('users')
            ->whereIn('negocio_id', $negocioIds)
            ->where('rol', Role::Administrador->value)
            ->orderBy('nombre')
            ->get(['negocio_id', 'nombre', 'correo', 'activo', 'debe_cambiar_contrasena']);

        foreach ($rows as $row) {
            $byNegocio[(string) $row->negocio_id][] = new TenantAdministrator(
                (string) $row->nombre,
                (string) $row->correo,
                (bool) $row->activo,
                (bool) $row->debe_cambiar_contrasena,
            );
        }

        return $byNegocio;
    }

    /**
     * Turn a row of a negocio into its summary.
     *
     * @param  list<TenantAdministrator>  $administrators
     */
    private function summary(stdClass $row, array $administrators): TenantSummary
    {
        return new TenantSummary(
            (string) $row->id,
            (string) $row->nombre,
            $row->estado === NegocioModel::ACTIVE,
            CarbonImmutable::parse((string) $row->created_at),
            $administrators,
        );
    }
}
