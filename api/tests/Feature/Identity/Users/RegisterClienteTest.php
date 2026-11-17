<?php

declare(strict_types=1);

namespace Tests\Feature\Identity\Users;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\TestCase;

final class RegisterClienteTest extends TestCase
{
    use RefreshDatabaseAsOwner;

    private const string CONFLICTO = 'http://localhost/problemas/conflicto';

    private const string DATOS_INVALIDOS = 'http://localhost/problemas/datos-invalidos';

    private const string NO_ENCONTRADO = 'http://localhost/problemas/no-encontrado';

    private NegocioModel $negocio;

    /** One active negocio where people register. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create();
    }

    /** A person registers without a token and becomes a cliente of that negocio, with the password hashed. */
    public function test_a_person_registers_as_cliente_of_a_negocio(): void
    {
        $response = $this->postJson($this->url(), $this->data(['correo' => ' ANA@Correo.test ']));

        $response
            ->assertCreated()
            ->assertJsonPath('data.nombre', 'Ana Rojas')
            ->assertJsonPath('data.correo', 'ana@correo.test')
            ->assertJsonPath('data.rol', 'cliente')
            ->assertJsonPath('data.abilities', ['comprar'])
            ->assertJsonPath('data.negocio_id', $this->negocio->id)
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.token');

        $user = UserModel::query()->where('correo', 'ana@correo.test')->sole();
        $this->assertSame('cliente', $user->rol);
        $this->assertSame($this->negocio->id, $user->negocio_id);
        $this->assertTrue($user->activo);
        $this->assertNotSame('secreto-123', $user->password);
        $this->assertTrue(Hash::check('secreto-123', $user->password));
    }

    /** The new cliente logs in, its token only carries `comprar`, and the routes of the staff refuse it. */
    public function test_the_new_cliente_logs_in_and_cannot_use_the_routes_of_the_staff(): void
    {
        $this->postJson($this->url(), $this->data())->assertCreated();

        $login = $this->postJson('/api/v1/tokens', [
            'correo' => 'ana@correo.test',
            'contrasena' => 'secreto-123',
            'dispositivo' => 'Teléfono de Ana',
        ]);
        $login->assertCreated()->assertJsonPath('data.abilities', ['comprar']);

        $this->app['auth']->forgetGuards();
        $token = $login->json('data.token');
        $this->assertIsString($token);

        foreach (['/productos', '/ventas', '/clientes', '/negocio', '/reportes/cierre-diario'] as $uri) {
            $this->withToken($token)->getJson('/api/v1'.$uri)->assertForbidden();
        }
    }

    /** The role and the state never come from the request: asking to be an administrator changes nothing. */
    public function test_the_role_cannot_be_chosen(): void
    {
        $this->postJson($this->url(), $this->data(['rol' => 'administrador', 'role' => 'administrador', 'abilities' => ['administrar']]))
            ->assertCreated()
            ->assertJsonPath('data.rol', 'cliente')
            ->assertJsonPath('data.abilities', ['comprar']);

        $this->assertSame('cliente', UserModel::query()->where('correo', 'ana@correo.test')->sole()->rol);
    }

    /** An email has one account only, written in any case; nothing is created or changed. */
    public function test_an_email_already_registered_answers_conflict(): void
    {
        $existing = UserModel::factory()->create(['correo' => 'ana@correo.test', 'nombre' => 'Ana de antes']);

        $this->postJson($this->url(), $this->data(['correo' => 'ANA@correo.test']))
            ->assertConflict()
            ->assertJsonPath('type', self::CONFLICTO);

        $this->assertSame(1, UserModel::query()->where('correo', 'ana@correo.test')->count());
        $this->assertSame('Ana de antes', $existing->refresh()->nombre);
    }

    /** A negocio that does not exist and an inactive one answer the same 404, and no account is created. */
    public function test_a_missing_or_inactive_negocio_answers_not_found(): void
    {
        $inactive = NegocioModel::factory()->create(['estado' => 'inactivo']);

        foreach ([Str::uuid()->toString(), $inactive->id] as $negocio) {
            $this->postJson("/api/v1/negocios/{$negocio}/registro", $this->data())
                ->assertNotFound()
                ->assertJsonPath('type', self::NO_ENCONTRADO);
        }

        $this->assertSame(0, UserModel::query()->where('correo', 'ana@correo.test')->count());
    }

    /**
     * Data that is missing or wrong answers 422 naming the field, and no account is created.
     *
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidData')]
    public function test_invalid_data_answers_unprocessable(array $overrides, string $field): void
    {
        $this->postJson($this->url(), $this->data($overrides))
            ->assertUnprocessable()
            ->assertJsonPath('type', self::DATOS_INVALIDOS)
            ->assertJsonStructure(['errores' => [$field]]);

        $this->assertSame(0, UserModel::query()->count());
    }

    /**
     * Payloads that must be refused and the field each one fails on.
     *
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidData(): array
    {
        return [
            'sin nombre' => [['nombre' => ''], 'nombre'],
            'nombre muy largo' => [['nombre' => str_repeat('a', 121)], 'nombre'],
            'sin correo' => [['correo' => ''], 'correo'],
            'correo mal escrito' => [['correo' => 'ana-sin-arroba'], 'correo'],
            'sin contraseña' => [['contrasena' => ''], 'contrasena'],
            'contraseña corta' => [['contrasena' => '1234567'], 'contrasena'],
            'contraseña muy larga' => [['contrasena' => str_repeat('a', 73)], 'contrasena'],
        ];
    }

    /** Address where people register in the negocio of the test. */
    private function url(): string
    {
        return "/api/v1/negocios/{$this->negocio->id}/registro";
    }

    /**
     * Valid data of a new cliente, with overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function data(array $overrides = []): array
    {
        return [...['nombre' => 'Ana Rojas', 'correo' => 'ana@correo.test', 'contrasena' => 'secreto-123'], ...$overrides];
    }
}
