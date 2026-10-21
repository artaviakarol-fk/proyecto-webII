<?php

declare(strict_types=1);

namespace Tests\Feature\Negocios\OpeningHours;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

final class ManageOpeningHoursTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private const string ENDPOINT = '/api/v1/negocio/horario';

    private NegocioModel $negocio;

    /** A negocio with an administrator acting on it. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->negocio = NegocioModel::factory()->create(['nombre' => 'Minisúper A']);
        $this->actAs(Role::Administrador);
    }

    /** The hours of the Minisúper Teresita: Monday to Friday 7:00-20:00, weekends 7:00-18:00. */
    private static function teresita(): array
    {
        $weekday = [['abre' => '07:00', 'cierra' => '20:00']];
        $weekend = [['abre' => '07:00', 'cierra' => '18:00']];

        return ['dias' => [
            'lunes' => $weekday, 'martes' => $weekday, 'miercoles' => $weekday, 'jueves' => $weekday, 'viernes' => $weekday,
            'sabado' => $weekend, 'domingo' => $weekend,
        ]];
    }

    /** Authenticate a member of the negocio with the abilities of the role. */
    private function actAs(Role $role): void
    {
        Sanctum::actingAs(UserModel::factory()->role($role)->create(['negocio_id' => $this->negocio->id]), $role->abilities());
    }

    /** A negocio that never set its hours is closed every day. */
    public function test_a_negocio_without_hours_is_closed_every_day(): void
    {
        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.dias.lunes', [])
            ->assertJsonPath('data.dias.domingo', [])
            ->assertJsonPath('data.abierto_ahora', false)
            ->assertJsonPath('data.zona_horaria', 'America/Costa_Rica');
    }

    /** The administration sets the weekly hours and every account of the negocio reads them back. */
    public function test_administrator_sets_the_hours_and_they_are_read_back(): void
    {
        $this->putJson(self::ENDPOINT, self::teresita())
            ->assertOk()
            ->assertJsonPath('data.dias.lunes.0.abre', '07:00')
            ->assertJsonPath('data.dias.domingo.0.cierra', '18:00');

        $this->actAs(Role::Cajero);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.dias.viernes', [['abre' => '07:00', 'cierra' => '20:00']])
            ->assertJsonPath('data.dias.sabado', [['abre' => '07:00', 'cierra' => '18:00']]);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('horarios_negocio', 7);
    }

    /** Setting the hours again replaces the previous ones, and a day left out stays closed. */
    public function test_setting_the_hours_again_replaces_the_previous_ones(): void
    {
        $this->putJson(self::ENDPOINT, self::teresita())->assertOk();
        $this->putJson(self::ENDPOINT, ['dias' => ['lunes' => [['abre' => '08:00', 'cierra' => '12:00'], ['abre' => '14:00', 'cierra' => '18:00']]]])->assertOk();

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.dias.lunes', [['abre' => '08:00', 'cierra' => '12:00'], ['abre' => '14:00', 'cierra' => '18:00']])
            ->assertJsonPath('data.dias.martes', [])
            ->assertJsonPath('data.dias.domingo', []);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('horarios_negocio', 2);
    }

    /** An empty list of days closes the negocio every day. */
    public function test_an_empty_schedule_closes_the_negocio(): void
    {
        $this->putJson(self::ENDPOINT, self::teresita())->assertOk();
        $this->putJson(self::ENDPOINT, ['dias' => []])->assertOk()->assertJsonPath('data.dias.lunes', []);

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('horarios_negocio', 0);
    }

    /** "Open now" is read in Costa Rica time (UTC-6), whatever the time zone of the server. */
    #[DataProvider('moments')]
    public function test_open_now_follows_the_hours_in_costa_rica_time(string $utcMoment, bool $expected): void
    {
        $this->putJson(self::ENDPOINT, self::teresita())->assertOk();

        $this->travelTo($utcMoment);

        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonPath('data.abierto_ahora', $expected);
    }

    /**
     * Moments in UTC, and whether the negocio is open: 2026-10-20 is a Tuesday and 2026-10-24 a Saturday.
     *
     * @return array<string, array{string, bool}>
     */
    public static function moments(): array
    {
        return [
            'tuesday one minute before opening' => ['2026-10-20T12:59:00Z', false],
            'tuesday at opening time' => ['2026-10-20T13:00:00Z', true],
            'tuesday at closing time' => ['2026-10-21T02:00:00Z', false],
            'saturday at 17:59' => ['2026-10-24T23:59:00Z', true],
            'saturday at closing time' => ['2026-10-25T00:00:00Z', false],
        ];
    }

    /** Every role of the negocio reads the hours, but only the administration changes them. */
    #[DataProvider('staffWithoutAdministration')]
    public function test_only_the_administration_changes_the_hours(Role $role): void
    {
        $this->actAs($role);

        $this->getJson(self::ENDPOINT)->assertOk();
        $this->putJson(self::ENDPOINT, self::teresita())
            ->assertForbidden()
            ->assertJsonPath('type', 'http://localhost/problemas/prohibido');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseCount('horarios_negocio', 0);
    }

    /**
     * The roles that are not the administration.
     *
     * @return array<string, array{Role}>
     */
    public static function staffWithoutAdministration(): array
    {
        return ['cajero' => [Role::Cajero], 'bodeguero' => [Role::Bodeguero]];
    }

    /** Without a token both endpoints answer the unauthenticated problem. */
    public function test_guest_receives_an_unauthenticated_problem(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson(self::ENDPOINT)
            ->assertUnauthorized()
            ->assertJsonPath('type', 'http://localhost/problemas/no-autenticado');
        $this->putJson(self::ENDPOINT, self::teresita())
            ->assertUnauthorized()
            ->assertJsonPath('type', 'http://localhost/problemas/no-autenticado');
    }

    /**
     * Hours that make no sense answer 422 and leave the stored hours as they were.
     *
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidPayloads')]
    public function test_invalid_hours_are_rejected_and_change_nothing(array $payload): void
    {
        $this->putJson(self::ENDPOINT, ['dias' => ['lunes' => [['abre' => '09:00', 'cierra' => '10:00']]]])->assertOk();

        $this->putJson(self::ENDPOINT, $payload)
            ->assertUnprocessable()
            ->assertJsonPath('type', 'http://localhost/problemas/datos-invalidos');

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(1, DB::table('horarios_negocio')->count());
    }

    /**
     * Invalid bodies: a missing field, an unknown day, bad times, a slot that closes before it opens, overlaps and too many slots.
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidPayloads(): array
    {
        $slot = static fn (string $opens, string $closes): array => ['abre' => $opens, 'cierra' => $closes];

        return [
            'without days' => [[]],
            'unknown day' => [['dias' => ['feriado' => [$slot('07:00', '12:00')]]]],
            'time out of range' => [['dias' => ['lunes' => [$slot('25:00', '26:00')]]]],
            'not a time' => [['dias' => ['lunes' => [$slot('temprano', '20:00')]]]],
            'missing closing time' => [['dias' => ['lunes' => [['abre' => '07:00']]]]],
            'closes before opening' => [['dias' => ['lunes' => [$slot('20:00', '07:00')]]]],
            'closes when it opens' => [['dias' => ['lunes' => [$slot('07:00', '07:00')]]]],
            'overlapping slots' => [['dias' => ['lunes' => [$slot('07:00', '12:00'), $slot('11:00', '20:00')]]]],
            'too many slots' => [['dias' => ['lunes' => [$slot('06:00', '08:00'), $slot('09:00', '11:00'), $slot('12:00', '14:00'), $slot('15:00', '17:00')]]]],
        ];
    }

    /** Each negocio has its own hours: the account never sees or changes the ones of another negocio. */
    public function test_hours_are_isolated_between_negocios(): void
    {
        $other = NegocioModel::factory()->create(['nombre' => 'Minisúper B']);
        $this->scopeQueriesTo($other->id);
        DB::table('horarios_negocio')->insert([
            'id' => (string) Str::uuid7(),
            'negocio_id' => $other->id,
            'dia_semana' => 3,
            'hora_apertura' => '05:00',
            'hora_cierre' => '23:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->getJson(self::ENDPOINT)->assertOk()->assertJsonPath('data.dias.miercoles', []);

        $this->putJson(self::ENDPOINT, self::teresita())->assertOk();

        $this->scopeQueriesTo($other->id);
        $this->assertSame(1, DB::table('horarios_negocio')->count());
        $this->assertSame('05:00:00', DB::table('horarios_negocio')->value('hora_apertura'));

        $this->scopeQueriesTo($this->negocio->id);
        $this->assertSame(7, DB::table('horarios_negocio')->count());
    }

    /** The demo data carries the real hours of the Minisúper Teresita. */
    public function test_demo_data_has_the_hours_of_the_minisuper_teresita(): void
    {
        $this->seed(DatabaseSeeder::class);
        Sanctum::actingAs(UserModel::query()->where('correo', 'admin@teresita.test')->firstOrFail(), Role::Administrador->abilities());

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.dias.lunes', [['abre' => '07:00', 'cierra' => '20:00']])
            ->assertJsonPath('data.dias.viernes', [['abre' => '07:00', 'cierra' => '20:00']])
            ->assertJsonPath('data.dias.sabado', [['abre' => '07:00', 'cierra' => '18:00']])
            ->assertJsonPath('data.dias.domingo', [['abre' => '07:00', 'cierra' => '18:00']]);
    }
}
