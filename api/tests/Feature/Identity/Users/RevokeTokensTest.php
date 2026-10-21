<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Users;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class RevokeTokensTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/tokens';

    /** The administration whose sessions every scenario opens and closes. */
    protected function setUp(): void
    {
        parent::setUp();

        UserModel::factory()->role(Role::Administrador)->create(['correo' => 'admin@minisuper.test']);
    }

    /** Logging out revokes only the token in use; the other device stays logged in. */
    public function test_logging_out_revokes_only_the_token_in_use(): void
    {
        $register = $this->login('admin@minisuper.test', 'Caja 1');
        $phone = $this->login('admin@minisuper.test', 'Celular');

        $this->deleteWithToken('/actual', $register)->assertNoContent();

        $this->assertSame(['Celular'], $this->tokenNames());
        $this->deleteWithToken('/actual', $register)->assertUnauthorized();
        $this->deleteWithToken('/actual', $phone)->assertNoContent();
    }

    /** Logging out everywhere revokes every token of the account and none of anyone else. */
    public function test_logging_out_everywhere_revokes_all_the_tokens_of_the_account(): void
    {
        UserModel::factory()->role(Role::Cajero)->create(['correo' => 'caja@minisuper.test']);
        $register = $this->login('admin@minisuper.test', 'Caja 1');
        $phone = $this->login('admin@minisuper.test', 'Celular');
        $cashier = $this->login('caja@minisuper.test', 'Caja 2');

        $this->deleteWithToken('', $phone)->assertNoContent();

        $this->assertSame(['Caja 2'], $this->tokenNames());
        $this->deleteWithToken('/actual', $register)->assertUnauthorized();
        $this->deleteWithToken('/actual', $cashier)->assertNoContent();
    }

    /** A revoked token answers the unauthenticated problem, on any endpoint, like a missing one. */
    public function test_revoked_token_answers_unauthenticated(): void
    {
        $token = $this->login('admin@minisuper.test', 'Caja 1');
        $this->deleteWithToken('/actual', $token)->assertNoContent();

        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/productos')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'http://localhost/problemas/no-autenticado');
    }

    /** Both ways of logging out need a token. */
    public function test_logging_out_requires_a_token(): void
    {
        foreach (['/actual', ''] as $path) {
            $this->deleteJson(self::ENDPOINT.$path)
                ->assertUnauthorized()
                ->assertJsonPath('type', 'http://localhost/problemas/no-autenticado');
        }
    }

    /** Log in through the API and return the plain text token. */
    private function login(string $email, string $device): string
    {
        return $this->postJson(self::ENDPOINT, ['correo' => $email, 'contrasena' => 'password', 'dispositivo' => $device])
            ->assertCreated()
            ->json('data.token');
    }

    /** Send a request with a token, forgetting the user the guards kept from the previous request. */
    private function deleteWithToken(string $path, string $token): TestResponse
    {
        Auth::forgetGuards();

        return $this->withToken($token)->deleteJson(self::ENDPOINT.$path);
    }

    /**
     * Devices that still have a token, in alphabetical order.
     *
     * @return list<string>
     */
    private function tokenNames(): array
    {
        return array_values(DB::table('personal_access_tokens')->orderBy('name')->pluck('name')->all());
    }
}
