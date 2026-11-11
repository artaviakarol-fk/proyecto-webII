<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Infrastructure\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Src\Sales\SaleDrafts\Application\DTOs\DraftLine;
use Src\Sales\SaleDrafts\Application\DTOs\InterpretedItem;
use Src\Sales\SaleDrafts\Application\DTOs\SaleDraft;

/**
 * @property-read SaleDraft $resource
 */
final class SaleDraftResource extends JsonResource
{
    /** Wrap the draft. */
    public function __construct(private readonly SaleDraft $draft)
    {
        parent::__construct($draft);
    }

    /**
     * The draft, its warnings and, under "confirmar", the bodies the cashier sends to register it by hand.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $draft = $this->draft;
        $customer = $draft->customer;

        return [
            'texto' => $draft->text,
            'metodo_pago' => $draft->paymentMethod,
            'cliente_mencionado' => $draft->customerWritten,
            'cliente' => $customer === null ? null : [
                'id' => $customer->id,
                'nombre' => $customer->name,
                'activo' => $customer->active,
                'saldo_actual' => $customer->balance->cents / 100,
                'disponible' => $customer->available->cents / 100,
            ],
            'lineas' => array_map(fn (DraftLine $line): array => [
                'producto_id' => $line->product->id,
                'nombre' => $line->product->name,
                'mencionado' => $line->written,
                'cantidad' => $line->quantity,
                'precio_unitario' => $line->product->price->cents / 100,
                'subtotal' => $line->subtotal->cents / 100,
                'disponible_para_vender' => $line->product->sellable,
                'alcanza' => $line->enoughStock,
            ], $draft->lines),
            'no_encontrados' => array_map(fn (InterpretedItem $item): array => ['mencionado' => $item->product, 'cantidad' => $item->quantity], $draft->notFound),
            'total' => $draft->total->cents / 100,
            'cabe_en_fiado' => $draft->fitsFiado,
            'avisos' => $draft->warnings,
            'listo_para_confirmar' => $draft->readyToConfirm(),
            'confirmar' => [
                'venta' => ['lineas' => array_map(fn (DraftLine $line): array => ['producto_id' => $line->product->id, 'cantidad' => $line->quantity], $draft->lines)],
                'fiado' => $draft->paymentMethod === 'fiado' && $customer !== null ? ['cliente_id' => $customer->id] : null,
            ],
        ];
    }
}
