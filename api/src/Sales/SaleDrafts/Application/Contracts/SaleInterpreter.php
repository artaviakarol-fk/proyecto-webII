<?php

declare(strict_types=1);

namespace Src\Sales\SaleDrafts\Application\Contracts;

use Src\Sales\SaleDrafts\Application\DTOs\InterpretedSale;
use Src\Sales\SaleDrafts\Domain\Exceptions\SaleInterpreterUnavailableException;
use Src\Sales\SaleDrafts\Domain\ValueObjects\SaleText;

/** The AI provider that reads the text of the cashier. */
interface SaleInterpreter
{
    /**
     * Turn the text into a structured sale, validated against a schema. The names of the catalog and
     * the customers are only hints: the matching is done later, against the data of the negocio.
     *
     * @param  list<string>  $productNames
     * @param  list<string>  $customerNames
     *
     * @throws SaleInterpreterUnavailableException when the provider fails, takes too long or answers something invalid
     */
    public function interpret(SaleText $text, array $productNames, array $customerNames): InterpretedSale;
}
