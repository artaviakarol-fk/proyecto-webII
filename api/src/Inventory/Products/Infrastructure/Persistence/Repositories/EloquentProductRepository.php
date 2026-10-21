<?php

declare(strict_types=1);

namespace Src\Inventory\Products\Infrastructure\Persistence\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Src\Inventory\Products\Domain\Contracts\ProductRepository;
use Src\Inventory\Products\Domain\Entities\Product;
use Src\Inventory\Products\Domain\Exceptions\ProductCodeAlreadyInUseException;
use Src\Inventory\Products\Domain\ValueObjects\Category;
use Src\Inventory\Products\Domain\ValueObjects\PriceChangeReason;
use Src\Inventory\Products\Domain\ValueObjects\ProductCode;
use Src\Inventory\Products\Domain\ValueObjects\ProductDetails;
use Src\Inventory\Products\Domain\ValueObjects\ProductName;
use Src\Inventory\Products\Domain\ValueObjects\ProductPage;
use Src\Inventory\Products\Domain\ValueObjects\Stock;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Inventory\Shared\Domain\ValueObjects\ProductId;
use Src\Inventory\Shared\Infrastructure\Persistence\PriceHistory;
use Src\Shared\Domain\Contracts\CurrentUser;
use Src\Shared\Domain\ValueObjects\NegocioId;

/**
 * Every query also filters by negocio_id: row-level security already isolates the negocios,
 * and the explicit filter keeps the intent visible in the code.
 */
final readonly class EloquentProductRepository implements ProductRepository
{
    /** Receive the account that performs the change, for the price history. */
    public function __construct(private CurrentUser $user) {}

    /** Generate a time-ordered UUID for a new product. */
    public function nextId(): ProductId
    {
        return new ProductId((string) Str::uuid7());
    }

    /** Insert or update the product inside a savepoint, record a price change and translate a repeated code. */
    public function save(Product $product, PriceChangeReason $reason = PriceChangeReason::Edited): void
    {
        $details = $product->details;

        try {
            DB::transaction(function () use ($product, $details, $reason): void {
                $before = ProductModel::query()
                    ->where('negocio_id', $product->negocioId->value)
                    ->whereKey($product->id->value)
                    ->first(['precio_venta', 'costo_actual']);

                ProductModel::query()->updateOrCreate(
                    ['id' => $product->id->value],
                    [
                        'negocio_id' => $product->negocioId->value,
                        'codigo' => $details->code->value,
                        'nombre' => $details->name->value,
                        'categoria' => $details->category?->value,
                        'precio_venta' => $details->salePrice->toDecimal(),
                        'costo_actual' => $details->currentCost->toDecimal(),
                        'stock' => $details->stock->units,
                        'es_perecedero' => $details->perishable,
                        'es_licor' => $details->liquor,
                        'foto_path' => $product->photoPath,
                    ],
                );

                $cost = $details->currentCost->toDecimal();
                $price = $details->salePrice->toDecimal();

                if ($before === null || $before->costo_actual !== $cost || $before->precio_venta !== $price) {
                    PriceHistory::record(
                        $product->negocioId->value,
                        $product->id->value,
                        $cost,
                        $price,
                        $before === null ? PriceHistory::CREATED : $reason->value,
                        $this->user->id(),
                    );
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw ProductCodeAlreadyInUseException::create();
        }
    }

    /** Find the live product scoped to its negocio. */
    public function find(ProductId $id, NegocioId $negocioId): ?Product
    {
        $model = ProductModel::query()
            ->where('negocio_id', $negocioId->value)
            ->find($id->value);

        return $model === null ? null : $this->toDomain($model);
    }

    /** One page of the live products of the negocio ordered by name, optionally matching a name or code. */
    public function search(NegocioId $negocioId, ?string $term, int $page, int $perPage): ProductPage
    {
        $query = ProductModel::query()
            ->where('negocio_id', $negocioId->value)
            ->when($term !== null, function (Builder $query) use ($term): void {
                $pattern = '%'.addcslashes((string) $term, '%_\\').'%';
                $query->where(fn (Builder $match) => $match
                    ->where('nombre', 'ilike', $pattern)
                    ->orWhere('codigo', 'ilike', $pattern));
            });

        $total = (clone $query)->count();
        $models = $query->orderBy('nombre')->orderBy('id')->forPage($page, $perPage)->get();

        return new ProductPage(array_values($models->map($this->toDomain(...))->all()), $page, $perPage, $total);
    }

    /** Soft delete the product: it leaves the catalog but its sales keep pointing to it. */
    public function delete(Product $product): void
    {
        ProductModel::query()
            ->where('negocio_id', $product->negocioId->value)
            ->whereKey($product->id->value)
            ->delete();
    }

    /** Rebuild the aggregate from its stored state. */
    private function toDomain(ProductModel $model): Product
    {
        return Product::reconstitute(
            new ProductId($model->id),
            new NegocioId($model->negocio_id),
            new ProductDetails(
                new ProductCode($model->codigo),
                new ProductName($model->nombre),
                $model->categoria === null ? null : new Category($model->categoria),
                Money::fromDecimal($model->precio_venta),
                Money::fromDecimal($model->costo_actual),
                new Stock($model->stock),
                $model->es_perecedero,
                $model->es_licor,
            ),
            $model->foto_path,
        );
    }
}
