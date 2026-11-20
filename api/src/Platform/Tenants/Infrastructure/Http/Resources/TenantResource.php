<?php

declare(strict_types=1);

namespace Src\Platform\Tenants\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Platform\Tenants\Application\DTOs\TenantAdministrator;
use Src\Platform\Tenants\Application\DTOs\TenantSummary;

/**
 * @property-read TenantSummary $resource
 */
final class TenantResource extends JsonResource
{
    /** Wrap one negocio of the platform. */
    public function __construct(private readonly TenantSummary $tenant)
    {
        parent::__construct($tenant);
    }

    /**
     * What the platform sees of a negocio: never its sales, customers, fiado or stock.
     *
     * @return array{id: string, nombre: string, estado: string, creado_el: string, administradores: list<array{nombre: string, correo: string, activo: bool, debe_cambiar_contrasena: bool}>, servicio: array{estado: string|null, vence_el: string|null, dias_restantes: int|null, en_prueba: bool}}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->tenant->id,
            'nombre' => $this->tenant->name,
            'estado' => $this->tenant->active ? 'activo' : 'inactivo',
            'creado_el' => $this->tenant->openedAt->setTimezone(new \DateTimeZone((string) config('app.timezone')))->format(DATE_ATOM),
            'administradores' => array_map(fn (TenantAdministrator $administrator): array => [
                'nombre' => $administrator->name,
                'correo' => $administrator->email,
                'activo' => $administrator->active,
                'debe_cambiar_contrasena' => $administrator->mustChangePassword,
            ], $this->tenant->administrators),
            'servicio' => [
                'estado' => $this->tenant->status?->value,
                'vence_el' => $this->tenant->paidUntil?->setTimezone(new \DateTimeZone((string) config('app.timezone')))->format(DATE_ATOM),
                'dias_restantes' => $this->tenant->daysLeft,
                'en_prueba' => $this->tenant->trial,
            ],
        ];
    }
}
