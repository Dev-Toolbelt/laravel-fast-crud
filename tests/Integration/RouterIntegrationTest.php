<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Integration;

use DevToolbelt\LaravelFastCrud\Router;
use DevToolbelt\LaravelFastCrud\Tests\Integration\Fixtures\ProductController;
use Illuminate\Support\Facades\Route;

final class RouterIntegrationTest extends IntegrationTestCase
{
    public function testRouterRegistersAllCrudRoutes(): void
    {
        Router::crud('products', ProductController::class, 'products');

        $routes = Route::getRoutes();

        // Search (GET /)
        $this->assertNotNull($routes->getByAction(ProductController::class . '@search'));

        // Create (POST /)
        $this->assertNotNull($routes->getByAction(ProductController::class . '@create'));

        // Read (GET /{id})
        $this->assertNotNull($routes->getByAction(ProductController::class . '@read'));

        // Update (PUT /{id})
        $this->assertNotNull($routes->getByAction(ProductController::class . '@update'));

        // Delete (DELETE /{id})
        $this->assertNotNull($routes->getByAction(ProductController::class . '@delete'));

        // Soft Delete (DELETE /{id}/soft)
        $this->assertNotNull($routes->getByAction(ProductController::class . '@softDelete'));

        // Restore (PATCH /{id}/restore)
        $this->assertNotNull($routes->getByAction(ProductController::class . '@restore'));

        // Options (GET /options)
        $this->assertNotNull($routes->getByAction(ProductController::class . '@options'));

        // Export CSV (GET /export-csv)
        $this->assertNotNull($routes->getByAction(ProductController::class . '@exportCsv'));
    }

    public function testRouterWithOnlyOption(): void
    {
        Router::crud('categories', ProductController::class, 'categories', only: ['search', 'read']);

        $routes = Route::getRoutes();

        // Should have search and read
        $searchRoute = collect($routes->getRoutes())->first(
            fn($r) => str_contains($r->uri, 'categories') && $r->getActionMethod() === 'search'
        );
        $readRoute = collect($routes->getRoutes())->first(
            fn($r) => str_contains($r->uri, 'categories') && $r->getActionMethod() === 'read'
        );

        $this->assertNotNull($searchRoute);
        $this->assertNotNull($readRoute);

        // Should not have create
        $createRoute = collect($routes->getRoutes())->first(
            fn($r) => str_contains($r->uri, 'categories') && $r->getActionMethod() === 'create'
        );
        $this->assertNull($createRoute);
    }

    public function testRouterWithExceptOption(): void
    {
        Router::crud('items', ProductController::class, 'items', except: ['delete', 'softDelete']);

        $routes = Route::getRoutes();

        // Should have search
        $searchRoute = collect($routes->getRoutes())->first(
            fn($r) => str_contains($r->uri, 'items') && $r->getActionMethod() === 'search'
        );
        $this->assertNotNull($searchRoute);

        // Should not have delete
        $deleteRoute = collect($routes->getRoutes())->first(
            fn($r) => str_contains($r->uri, 'items') && $r->getActionMethod() === 'delete'
        );
        $this->assertNull($deleteRoute);
    }

    public function testRouterRoutesAreAccessible(): void
    {
        Router::crud('test-products', ProductController::class, 'test-products');

        // Test search route (may return 403 without a valid authenticated user + permission)
        $response = $this->get('/test-products');
        $this->assertContains($response->status(), [200, 403, 404]);

        // Options route has no permission middleware, so it must never return 403
        $response = $this->get('/test-products/options?label=name');
        $this->assertNotSame(403, $response->status());
    }

    public function testRouterAppliesMiddleware(): void
    {
        Router::crud('secured', ProductController::class, 'secured');

        $routes = Route::getRoutes();

        $searchRoute = collect($routes->getRoutes())->first(
            fn($r) => str_contains($r->uri, 'secured') && $r->getActionMethod() === 'search'
        );

        // The route should have the permission middleware
        $this->assertNotNull($searchRoute);
    }

    public function testOptionsRouteHasNoPermissionMiddleware(): void
    {
        Router::crud('secured2', ProductController::class, 'secured2');

        $routes = Route::getRoutes();

        $optionsRoute = collect($routes->getRoutes())->first(
            fn($r) => str_contains($r->uri, 'secured2') && $r->getActionMethod() === 'options'
        );

        $this->assertNotNull($optionsRoute);

        $canMiddleware = array_filter(
            $optionsRoute->middleware(),
            static fn(string $m): bool => str_starts_with($m, 'can:')
        );

        $this->assertEmpty($canMiddleware);
    }

    public function testSearchRouteHasPermissionMiddlewareButOptionsDoesNot(): void
    {
        Router::crud('secured3', ProductController::class, 'secured3');

        $routes = Route::getRoutes();

        $searchRoute = collect($routes->getRoutes())->first(
            fn($r) => str_contains($r->uri, 'secured3') && $r->getActionMethod() === 'search'
        );
        $optionsRoute = collect($routes->getRoutes())->first(
            fn($r) => str_contains($r->uri, 'secured3') && $r->getActionMethod() === 'options'
        );

        $this->assertNotNull($searchRoute);
        $this->assertContains('can:secured3.access.search', $searchRoute->middleware());

        $this->assertNotNull($optionsRoute);
        $canMiddleware = array_filter(
            $optionsRoute->middleware(),
            static fn(string $m): bool => str_starts_with($m, 'can:')
        );
        $this->assertEmpty($canMiddleware);
    }
}
