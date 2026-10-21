<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use Src\Identity\Users\Domain\Contracts\TokenRevoker;
use Src\Identity\Users\Domain\ValueObjects\UserId;

final class RecordingTokenRevoker implements TokenRevoker
{
    /** @var list<string> What was revoked: "user:token" for one token, "user:*" for all of them. */
    public array $revoked = [];

    /** Record the revocation of one token. */
    public function revoke(UserId $userId, string $tokenId): void
    {
        $this->revoked[] = "{$userId->value}:{$tokenId}";
    }

    /** Record the revocation of every token of the user. */
    public function revokeAll(UserId $userId): void
    {
        $this->revoked[] = "{$userId->value}:*";
    }
}
