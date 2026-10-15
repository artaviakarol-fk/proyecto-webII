<?php

declare(strict_types=1);

namespace Src\Identity\Users\Infrastructure\Security;

use Src\Identity\Users\Domain\Contracts\TokenIssuer;
use Src\Identity\Users\Domain\ValueObjects\UserId;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;

final readonly class SanctumTokenIssuer implements TokenIssuer
{
    /**
     * Create a Sanctum personal access token named after the device.
     *
     * @param  list<string>  $abilities
     */
    public function issue(UserId $userId, string $deviceName, array $abilities): string
    {
        return UserModel::query()
            ->findOrFail($userId->value)
            ->createToken($deviceName, $abilities)
            ->plainTextToken;
    }
}
