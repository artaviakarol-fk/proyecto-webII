<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\Users\Application;

use PHPUnit\Framework\TestCase;
use Src\Identity\Users\Application\UseCases\RevokeAllTokens;
use Src\Identity\Users\Application\UseCases\RevokeCurrentToken;
use Tests\Support\Identity\RecordingTokenRevoker;

final class RevokeTokensTest extends TestCase
{
    private const string USER_ID = '0192f0c4-7b1e-7c3a-9f1d-2b6a4e8c0d22';

    /** Logging out asks the port to revoke only the token of the request. */
    public function test_revokes_the_current_token(): void
    {
        $tokens = new RecordingTokenRevoker;

        new RevokeCurrentToken($tokens)->execute(self::USER_ID, '42');

        $this->assertSame([self::USER_ID.':42'], $tokens->revoked);
    }

    /** Logging out everywhere asks the port to revoke every token of the account. */
    public function test_revokes_every_token(): void
    {
        $tokens = new RecordingTokenRevoker;

        new RevokeAllTokens($tokens)->execute(self::USER_ID);

        $this->assertSame([self::USER_ID.':*'], $tokens->revoked);
    }
}
