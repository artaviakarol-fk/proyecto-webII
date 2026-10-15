<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Users;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class IssueTokenTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string ENDPOINT = '/api/v1/tokens';

    /** Each role logs in from a named device and receives a bearer token with its abilities. */
    #[DataProvider('roles')]
    public function test_each_role_receives_a_token_with_its_abilities(Role $role, array $abilities): void
    {
        $user = UserModel::factory()->role($role)->create(['correo' => 'ana@minisuper.test']);

        $response = $this->postJson(self::ENDPOINT, $this->credentials(['correo' => ' ANA@Minisuper.test ']));

        $token = $response->json('data.token');
        $response
            ->assertCreated()
            ->assertExactJson(['data' => ['token' => $token, 'tipo' => 'Bearer', 'abilities' => $abilities]]);

        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'Caja 1']);
        $this->assertTrue(PersonalAccessToken::findToken($token)?->tokenable?->is($user));
    }

    /**
     * Roles and the abilities their tokens carry.
     *
     * @return array<string, array{Role, list<string>}>
     */
    public static function roles(): array
    {
        return [
            'administrador' => [Role::Administrador, ['administrar', 'caja', 'inventario']],
            'cajero' => [Role::Cajero, ['caja']],
            'bodeguero' => [Role::Bodeguero, ['inventario']],
        ];
    }

    /** A wrong password, an unknown email and an inactive account answer the very same problem. */
    #[DataProvider('invalidCredentials')]
    public function test_invalid_credentials_answer_the_same_problem(string $email, string $password): void
    {
        UserModel::factory()->create(['correo' => 'ana@minisuper.test']);
        UserModel::factory()->inactive()->create(['correo' => 'inactiva@minisuper.test']);

        $response = $this->postJson(self::ENDPOINT, $this->credentials(['correo' => $email, 'contrasena' => $password]));

        $response
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json');

        $this->assertSame([
            'type' => 'http://localhost/problemas/credenciales-invalidas',
            'title' => 'Credenciales inválidas',
            'status' => 401,
            'detail' => 'El correo o la contraseña no son correctos.',
        ], Arr::except($response->json(), 'instance'));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * Credentials that must not receive a token.
     *
     * @return array<string, array{string, string}>
     */
    public static function invalidCredentials(): array
    {
        return [
            'contraseña incorrecta' => ['ana@minisuper.test', 'otraclave456'],
            'correo desconocido' => ['nadie@minisuper.test', 'password'],
            'cuenta inactiva' => ['inactiva@minisuper.test', 'password'],
        ];
    }

    /** Malformed fields are reported one by one in Spanish. */
    #[DataProvider('invalidPayloads')]
    public function test_malformed_data_is_rejected(string $field, mixed $value, string $message): void
    {
        $this->postJson(self::ENDPOINT, $this->credentials([$field => $value]))
            ->assertUnprocessable()
            ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos')
            ->assertJsonPath("errores.{$field}.0", $message);
    }

    /**
     * Invalid values for a field and the message each one produces.
     *
     * @return array<string, array{string, mixed, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'sin correo' => ['correo', null, 'El campo correo es obligatorio.'],
            'correo mal formado' => ['correo', 'ana.minisuper.test', 'El campo correo debe ser un correo electrónico válido.'],
            'sin contraseña' => ['contrasena', null, 'El campo contraseña es obligatorio.'],
            'sin dispositivo' => ['dispositivo', null, 'El campo dispositivo es obligatorio.'],
            'dispositivo muy largo' => ['dispositivo', str_repeat('a', 101), 'El campo dispositivo no debe tener más de 100 caracteres.'],
        ];
    }

    /** The password travels untouched: spaces at its ends are part of it. */
    public function test_password_is_not_trimmed(): void
    {
        UserModel::factory()->create(['correo' => 'ana@minisuper.test', 'password' => Hash::make(' clave con espacios ')]);

        $this->postJson(self::ENDPOINT, $this->credentials(['contrasena' => ' clave con espacios ']))->assertCreated();
        $this->postJson(self::ENDPOINT, $this->credentials(['contrasena' => 'clave con espacios']))->assertUnauthorized();
    }

    /**
     * Valid credentials with some fields replaced.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function credentials(array $overrides = []): array
    {
        return [
            'correo' => 'ana@minisuper.test',
            'contrasena' => 'password',
            'dispositivo' => 'Caja 1',
            ...$overrides,
        ];
    }
}
