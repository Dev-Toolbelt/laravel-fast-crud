<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud;

use Illuminate\Support\Facades\Route;

final class Router extends Route
{
    public static function crud(
        string $uri,
        string $controllerName,
        string $moduleName,
        array $except = [],
        array $only = []
    ): void {
        $actions = [
            ['verb' => 'get', 'path' => $uri, 'method' => 'search', 'permission' => 'search'],
            ['verb' => 'get', 'path' => "{$uri}/options", 'method' => 'options', 'permission' => 'search'],
            ['verb' => 'post', 'path' => $uri, 'method' => 'create', 'permission' => 'create'],
            ['verb' => 'get', 'path' => "{$uri}/export-csv", 'method' => 'exportCsv', 'permission' => 'exportCsv'],
            ['verb' => 'get', 'path' => "{$uri}/{id:uuid}", 'method' => 'read', 'permission' => 'view'],
            ['verb' => 'put', 'path' => "{$uri}/{id:uuid}", 'method' => 'update', 'permission' => 'update'],
            ['verb' => 'patch', 'path' => "{$uri}/{id:uuid}", 'method' => 'update', 'permission' => 'update'],
            ['verb' => 'post', 'path' => "{$uri}/{id:uuid}", 'method' => 'update', 'permission' => 'update'],
            ['verb' => 'delete', 'path' => "{$uri}/{id:uuid}", 'method' => 'delete', 'permission' => 'delete'],
        ];

        foreach ($actions as $action) {
            $verb = $action['verb'];
            $path = $action['path'];
            $method = $action['method'];
            $permission = $action['permission'] ?? null;

            if (!method_exists($controllerName, $method)) {
                continue;
            }

            if (!empty($only)) {
                if (!in_array($method, $only)) {
                    continue;
                }
            } elseif (in_array($method, $except)) {
                continue;
            }

            if (empty($permission)) {
                static::$verb($path, "{$controllerName}@{$method}");
                continue;
            }

            static::$verb($path, "{$controllerName}@{$method}")
                ->middleware("can:{$moduleName}.access.{$permission}");
        }
    }
}
