<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** The API documentation (Scramble) opens without a token and describes every endpoint. */
final class OpenApiContractTest extends TestCase
{
    private const string API_PREFIX = 'api/v1';

    /** The page of the documentation opens without a token. */
    public function test_documentation_page_opens_without_token(): void
    {
        $this->get('/docs/api')->assertOk();
    }

    /**
     * Every route of the API is in the OpenAPI document, so a new endpoint cannot be left undocumented.
     */
    public function test_every_route_of_the_api_is_documented(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();

        $expected = [];

        foreach ($this->apiRoutes() as $route) {
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $expected[] = strtolower($method).' '.$this->documentedPath($route);
            }
        }

        $documented = [];

        foreach ($spec['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                $documented[] = $method.' '.$path;
            }
        }

        $this->assertNotEmpty($expected);
        $this->assertSame([], array_values(array_diff($expected, $documented)), 'Endpoints missing from the documentation.');
    }

    /** The document describes the bearer token and the problem answers. */
    public function test_document_has_the_token_and_the_problem_answers(): void
    {
        $spec = $this->getJson('/docs/api.json')->assertOk()->json();

        $this->assertSame('bearer', $spec['components']['securitySchemes']['http']['scheme']);
        $this->assertArrayHasKey('Problema', $spec['components']['schemas']);

        $sale = $spec['paths']['/ventas/{venta}/cobrar']['post']['responses'];

        foreach (['401', '403', '404', '409'] as $status) {
            $this->assertArrayHasKey($status, $sale);
            $this->assertArrayHasKey('application/problem+json', $sale[$status]['content']);
        }

        $login = $spec['paths']['/tokens']['post']['responses'];
        $this->assertArrayHasKey('401', $login);
        $this->assertArrayHasKey('429', $login);
        $this->assertArrayNotHasKey('403', $login);
    }

    /** The documentation never starts a session: the API has no sessions table, so a session would break the page. */
    public function test_documentation_routes_do_not_start_a_session(): void
    {
        $routes = array_filter(
            Route::getRoutes()->getRoutes(),
            fn (LaravelRoute $route): bool => in_array($route->uri(), ['docs/api', 'docs/api.json'], true),
        );

        $this->assertCount(2, $routes);

        foreach ($routes as $route) {
            $middleware = app('router')->resolveMiddleware($route->gatherMiddleware(), $route->excludedMiddleware());

            $this->assertNotContains(StartSession::class, $middleware, "The route {$route->uri()} starts a session.");
        }
    }

    /**
     * The routes under /api/v1.
     *
     * @return list<LaravelRoute>
     */
    private function apiRoutes(): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn (LaravelRoute $route): bool => $route->uri() === self::API_PREFIX || str_starts_with($route->uri(), self::API_PREFIX.'/'),
        ));
    }

    /** The path of a route in the document, which is relative to the server /api/v1. */
    private function documentedPath(LaravelRoute $route): string
    {
        return '/'.ltrim(substr($route->uri(), strlen(self::API_PREFIX)), '/');
    }
}
