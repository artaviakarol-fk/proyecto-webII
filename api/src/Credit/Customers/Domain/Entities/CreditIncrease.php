<?php

declare(strict_types=1);

namespace Src\Credit\Customers\Domain\Entities;

use DateTimeImmutable;
use DateTimeZone;
use Src\Credit\Customers\Domain\Exceptions\CreditIncreaseNotRevertibleException;
use Src\Credit\Customers\Domain\ValueObjects\CreditIncreaseId;
use Src\Credit\Customers\Domain\ValueObjects\CustomerId;
use Src\Credit\Customers\Domain\ValueObjects\IncreaseReason;
use Src\Credit\Customers\Domain\ValueObjects\LimitIncrease;
use Src\Shared\Domain\ValueObjects\Money;
use Src\Shared\Domain\ValueObjects\NegocioId;

/**
 * US-304: an exceptional increase of the limit of fiado that a cashier gives in an emergency.
 *
 * It lasts until the end of the day in Costa Rica: the owner is back the next day and decides
 * whether the customer needs a higher base limit. It keeps who gave it, when, how much and why,
 * and the administration can revert it while it lasts.
 */
final class CreditIncrease
{
    public const string TIMEZONE = 'America/Costa_Rica';

    /** Hold the full state of the increase. */
    private function __construct(
        public readonly CreditIncreaseId $id,
        public readonly NegocioId $negocioId,
        public readonly CustomerId $customerId,
        public readonly Money $amount,
        public readonly IncreaseReason $reason,
        public readonly string $grantedBy,
        public readonly DateTimeImmutable $grantedAt,
        public readonly DateTimeImmutable $validUntil,
        public private(set) ?DateTimeImmutable $revertedAt,
        public private(set) ?string $revertedBy,
    ) {}

    /** Give an increase now; it counts until the next midnight in Costa Rica. Only a customer can give it, after checking the maximum. */
    public static function grant(
        CreditIncreaseId $id,
        NegocioId $negocioId,
        CustomerId $customerId,
        Money $amount,
        IncreaseReason $reason,
        string $grantedBy,
        DateTimeImmutable $at,
    ): self {
        return new self($id, $negocioId, $customerId, $amount, $reason, $grantedBy, $at, self::endOfDay($at), null, null);
    }

    /** Rebuild an increase from its stored state. */
    public static function reconstitute(
        CreditIncreaseId $id,
        NegocioId $negocioId,
        CustomerId $customerId,
        Money $amount,
        IncreaseReason $reason,
        string $grantedBy,
        DateTimeImmutable $grantedAt,
        DateTimeImmutable $validUntil,
        ?DateTimeImmutable $revertedAt,
        ?string $revertedBy,
    ): self {
        return new self($id, $negocioId, $customerId, $amount, $reason, $grantedBy, $grantedAt, $validUntil, $revertedAt, $revertedBy);
    }

    /** The next midnight in Costa Rica after a moment. */
    public static function endOfDay(DateTimeImmutable $at): DateTimeImmutable
    {
        return $at->setTimezone(new DateTimeZone(self::TIMEZONE))->modify('tomorrow');
    }

    /** Tell whether the increase counts at a moment: not reverted and not expired. */
    public function isActiveAt(DateTimeImmutable $at): bool
    {
        return $this->revertedAt === null && $at < $this->validUntil;
    }

    /** What the increase adds to the limit while it counts. */
    public function term(): LimitIncrease
    {
        return new LimitIncrease($this->amount, $this->validUntil);
    }

    /** The administration takes the increase back; one that was already reverted or has expired cannot be. */
    public function revert(string $userId, DateTimeImmutable $at): void
    {
        if ($this->revertedAt !== null) {
            throw CreditIncreaseNotRevertibleException::alreadyReverted();
        }

        if ($at >= $this->validUntil) {
            throw CreditIncreaseNotRevertibleException::expired();
        }

        $this->revertedAt = $at;
        $this->revertedBy = $userId;
    }
}
