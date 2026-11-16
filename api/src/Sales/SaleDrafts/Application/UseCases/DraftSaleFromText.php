<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Application\UseCases;

use DateTimeImmutable;
use Src\Sales\SaleDrafts\Application\Contracts\DraftCatalog;
use Src\Sales\SaleDrafts\Application\Contracts\DraftCustomers;
use Src\Sales\SaleDrafts\Application\Contracts\SaleInterpreter;
use Src\Sales\SaleDrafts\Application\DTOs\DraftCustomer;
use Src\Sales\SaleDrafts\Application\DTOs\DraftLine;
use Src\Sales\SaleDrafts\Application\DTOs\DraftProduct;
use Src\Sales\SaleDrafts\Application\DTOs\InterpretedItem;
use Src\Sales\SaleDrafts\Application\DTOs\SaleDraft;
use Src\Sales\SaleDrafts\Domain\Services\NameMatcher;
use Src\Sales\SaleDrafts\Domain\ValueObjects\SaleText;
use Src\Shared\Domain\Contracts\NegocioContext;
use Src\Shared\Domain\ValueObjects\Money;

/**
 * US-308: the cashier writes "anote 2 leches y 1 pan a doña María en fiado" and gets a draft of the sale.
 *
 * The AI only turns the text into a structured answer that is validated with a schema. Everything else
 * is decided here against the real data of the negocio: which products and which customer the names
 * mean, whether the stock is enough and whether the sale fits the limit of fiado. Nothing is stored:
 * the sale is registered only when the cashier confirms it with the usual endpoints.
 */
final readonly class DraftSaleFromText
{
    /** Receive the negocio of the operation, the AI provider, the catalog and the customers. */
    public function __construct(
        private NegocioContext $context,
        private SaleInterpreter $interpreter,
        private DraftCatalog $catalog,
        private DraftCustomers $customers,
    ) {}

    /** Build the draft; if the provider fails or answers something invalid, the exception says so and nothing changes. */
    public function execute(string $text, DateTimeImmutable $at): SaleDraft
    {
        $text = new SaleText($text);
        $products = $this->catalog->products($this->context->current());
        $customers = $this->customers->all($at);

        $interpreted = $this->interpreter->interpret(
            $text,
            array_map(fn (DraftProduct $product): string => $product->name, $products),
            array_map(fn (DraftCustomer $customer): string => $customer->name, $customers),
        );

        $warnings = [];
        [$lines, $notFound] = $this->lines($interpreted->items, $products, $warnings);
        $total = array_reduce($lines, fn (Money $sum, DraftLine $line): Money => $sum->plus($line->subtotal), new Money(0));

        $customer = $this->customer($interpreted->customer, $customers, $warnings);
        $method = $interpreted->paymentMethod ?? ($interpreted->customer !== null ? 'fiado' : 'efectivo');
        $fits = $method === 'fiado' ? $this->fitsFiado($customer, $interpreted->customer, $total, $warnings) : null;

        return new SaleDraft($text->value, $method, $interpreted->customer, $customer, $lines, $notFound, $total, $fits, $warnings);
    }

    /**
     * Match each product the cashier named with one of the catalog, and check its stock.
     *
     * @param  list<InterpretedItem>  $items
     * @param  list<DraftProduct>  $products
     * @param  list<string>  $warnings
     * @return array{0: list<DraftLine>, 1: list<InterpretedItem>}
     */
    private function lines(array $items, array $products, array &$warnings): array
    {
        $byName = [];

        foreach ($products as $product) {
            $byName[$product->name] = $product;
        }

        $lines = [];
        $notFound = [];
        $used = [];

        foreach ($items as $item) {
            $match = NameMatcher::best($item->product, $byName);

            if ($match === null) {
                $notFound[] = $item;
                $warnings[] = "No se encontró \"{$item->product}\" en el catálogo; agréguelo a mano.";

                continue;
            }

            [$product, $ambiguous] = $match;

            if ($ambiguous) {
                $warnings[] = "\"{$item->product}\" se parece a varios productos; revise que {$product->name} sea el correcto.";
            }

            if (isset($used[$product->id])) {
                $warnings[] = "{$product->name} aparece más de una vez; júntelo en una sola línea.";
            }

            $used[$product->id] = true;
            $enough = $item->quantity <= $product->sellable;

            if (! $enough) {
                $warnings[] = "No alcanza el stock de {$product->name}: se piden {$item->quantity} y hay {$product->sellable} para vender.";
            }

            $lines[] = new DraftLine($product, $item->product, $item->quantity, $product->price->times($item->quantity), $enough);
        }

        if ($lines === []) {
            $warnings[] = 'El borrador no tiene ningún producto del catálogo.';
        }

        return [$lines, $notFound];
    }

    /**
     * Match the customer the cashier named, if any, with one of the negocio.
     *
     * @param  list<DraftCustomer>  $customers
     * @param  list<string>  $warnings
     */
    private function customer(?string $written, array $customers, array &$warnings): ?DraftCustomer
    {
        if ($written === null) {
            return null;
        }

        $byName = [];

        foreach ($customers as $customer) {
            $byName[$customer->name] = $customer;
        }

        $match = NameMatcher::best($written, $byName);

        if ($match === null) {
            $warnings[] = "No se encontró al cliente \"{$written}\"; selecciónelo a mano.";

            return null;
        }

        [$customer, $ambiguous] = $match;

        if ($ambiguous) {
            $warnings[] = "\"{$written}\" se parece a varios clientes; revise que {$customer->name} sea el correcto.";
        }

        return $customer;
    }

    /**
     * On fiado, the customer has to be found, be active and the total has to fit what they can still owe.
     *
     * @param  list<string>  $warnings
     */
    private function fitsFiado(?DraftCustomer $customer, ?string $written, Money $total, array &$warnings): bool
    {
        if ($customer === null) {
            if ($written === null) {
                $warnings[] = 'Una venta a fiado necesita un cliente; selecciónelo a mano.';
            }

            return false;
        }

        if (! $customer->active) {
            $warnings[] = "{$customer->name} está inactivo y no puede comprar a fiado.";

            return false;
        }

        if ($total->cents > $customer->available->cents) {
            $warnings[] = "La venta no cabe en el fiado de {$customer->name}: le quedan ₡".number_format($customer->available->cents / 100, 0, ',', ' ').' disponibles.';

            return false;
        }

        return true;
    }
}
