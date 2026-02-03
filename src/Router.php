<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud;

use Illuminate\Support\Facades\Route;

/**
 * Route registration helper for CRUD controllers.
 *
 * Provides a convenient method to register all RESTful routes for a CRUD controller
 * with automatic permission middleware assignment.
 *
 * @example
 * ```php
 * // Register all CRUD routes for products
 * Router::crud('products', ProductController::class, 'products');
 *
 * // Exclude specific actions
 * Router::crud('categories', CategoryController::class, 'categories', except: ['delete', 'exportCsv']);
 *
 * // Include only specific actions
 * Router::crud('tags', TagController::class, 'tags', only: ['search', 'read']);
 * ```
 */
final class Router extends Route
{
    /**
     * Default CRUD action definitions.
     *
     * @var array<int, array{verb: string, path: string, method: string, permission: string}>
     */
    private const CRUD_ACTIONS = [
        ['verb' => 'get', 'path' => '', 'method' => 'search', 'permission' => 'search'],
        ['verb' => 'get', 'path' => '/options', 'method' => 'options', 'permission' => 'search'],
        ['verb' => 'post', 'path' => '', 'method' => 'create', 'permission' => 'create'],
        ['verb' => 'get', 'path' => '/export-csv', 'method' => 'exportCsv', 'permission' => 'exportCsv'],
        ['verb' => 'get', 'path' => '/{id:uuid}', 'method' => 'read', 'permission' => 'view'],
        ['verb' => 'put', 'path' => '/{id:uuid}', 'method' => 'update', 'permission' => 'update'],
        ['verb' => 'patch', 'path' => '/{id:uuid}', 'method' => 'update', 'permission' => 'update'],
        ['verb' => 'post', 'path' => '/{id:uuid}', 'method' => 'update', 'permission' => 'update'],
        ['verb' => 'delete', 'path' => '/{id:uuid}', 'method' => 'delete', 'permission' => 'delete'],
        ['verb' => 'delete', 'path' => '/{id:uuid}/soft', 'method' => 'softDelete', 'permission' => 'delete'],
        ['verb' => 'patch', 'path' => '/{id:uuid}/restore', 'method' => 'restore', 'permission' => 'restore'],
        ['verb' => 'put', 'path' => '/{id:uuid}/restore', 'method' => 'restore', 'permission' => 'restore'],
    ];

    /**
     * Register CRUD routes for a controller with permission middleware.
     *
     * Generates the following routes:
     * - GET /{uri} -> search() with {module}.access.search permission
     * - GET /{uri}/options -> options() with {module}.access.search permission
     * - POST /{uri} -> create() with {module}.access.create permission
     * - GET /{uri}/export-csv -> exportCsv() with {module}.access.exportCsv permission
     * - GET /{uri}/{id:uuid} -> read() with {module}.access.view permission
     * - PUT|PATCH|POST /{uri}/{id:uuid} -> update() with {module}.access.update permission
     * - DELETE /{uri}/{id:uuid} -> delete() with {module}.access.delete permission
     * - DELETE /{uri}/{id:uuid}/soft -> softDelete() with {module}.access.delete permission
     * - PUT|PATCH /{uri}/{id:uuid}/restore -> restore() with {module}.access.restore permission
     *
     * @param string $uri Base URI for the resource (e.g., 'products', 'api/v1/users')
     * @param class-string $controllerName Fully qualified controller class name
     * @param string $moduleName Module name used for permission middleware (e.g., 'products')
     * @param array<int, string> $except Action methods to exclude (e.g., ['delete', 'exportCsv'])
     * @param array<int, string> $only If provided, only these action methods will be registered
     */
    public static function crud(
        string $uri,
        string $controllerName,
        string $moduleName,
        array $except = [],
        array $only = []
    ): void {
        foreach (self::CRUD_ACTIONS as $action) {
            $verb = $action['verb'];
            $path = $uri . $action['path'];
            $method = $action['method'];
            $permission = $action['permission'];

            if (!method_exists($controllerName, $method)) {
                continue;
            }

            if (!self::shouldRegisterAction($method, $only, $except)) {
                continue;
            }

            static::$verb($path, "{$controllerName}@{$method}")
                ->middleware("can:{$moduleName}.access.{$permission}");
        }
    }

    /**
     * Determines if an action should be registered based on only/except filters.
     *
     * @param string $method The action method name
     * @param array<int, string> $only Actions to include (if not empty, only these are registered)
     * @param array<int, string> $except Actions to exclude
     */
    private static function shouldRegisterAction(string $method, array $only, array $except): bool
    {
        if (!empty($only)) {
            return in_array($method, $only, true);
        }

        return !in_array($method, $except, true);
    }
}
