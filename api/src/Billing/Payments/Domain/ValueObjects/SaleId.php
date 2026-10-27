<?php

declare(strict_types=1);

namespace Src\Billing\Payments\Domain\ValueObjects;

use Src\Shared\Domain\ValueObjects\Identifier;

/** The sale a payment belongs to, as the payments know it: only its identifier. */
final readonly class SaleId extends Identifier {}
