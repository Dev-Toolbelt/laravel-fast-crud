<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit;

use DevToolbelt\LaravelFastCrud\Router;
use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use DevToolbelt\LaravelFastCrud\Tests\Unit\Fixtures\RouterTestController;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Routing\Router as IlluminateRouter;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Route as RouteFacade;

final class RouterTest extends TestCase
{
    public function testRouterExtendsRoute(): void
    {
        $this->assertTrue(is_subclass_of(Router::class, RouteFacade::class));
    }

    public function testShouldRegisterActionWithOnlyFilter(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('shouldRegisterAction');
        $method->setAccessible(true);

        // When only is specified, should only include those actions
        $result = $method->invoke(null, 'search', ['search', 'read'], []);
        $this->assertTrue($result);

        $result = $method->invoke(null, 'create', ['search', 'read'], []);
        $this->assertFalse($result);
    }

    public function testShouldRegisterActionWithExceptFilter(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('shouldRegisterAction');
        $method->setAccessible(true);

        // When except is specified, should exclude those actions
        $result = $method->invoke(null, 'delete', [], ['delete']);
        $this->assertFalse($result);

        $result = $method->invoke(null, 'search', [], ['delete']);
        $this->assertTrue($result);
    }

    public function testShouldRegisterActionWithNoFilters(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $method = $reflection->getMethod('shouldRegisterAction');
        $method->setAccessible(true);

        // When no filters, should include all actions
        $result = $method->invoke(null, 'search', [], []);
        $this->assertTrue($result);

        $result = $method->invoke(null, 'delete', [], []);
        $this->assertTrue($result);
    }

    public function testCrudActionsConstantHasExpectedActions(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $constant = $reflection->getConstant('CRUD_ACTIONS');

        $methods = array_column($constant, 'method');

        $this->assertContains('search', $methods);
        $this->assertContains('options', $methods);
        $this->assertContains('create', $methods);
        $this->assertContains('exportCsv', $methods);
        $this->assertContains('read', $methods);
        $this->assertContains('update', $methods);
        $this->assertContains('delete', $methods);
        $this->assertContains('softDelete', $methods);
        $this->assertContains('restore', $methods);
    }

    public function testCrudActionsHasCorrectPermissions(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $constant = $reflection->getConstant('CRUD_ACTIONS');

        $permissions = [];
        foreach ($constant as $action) {
            $permissions[$action['method']] = $action['permission'];
        }

        $this->assertSame('search', $permissions['search']);
        $this->assertSame('create', $permissions['create']);
        $this->assertSame('view', $permissions['read']);
        $this->assertSame('update', $permissions['update']);
        $this->assertSame('delete', $permissions['delete']);
        $this->assertSame('delete', $permissions['softDelete']);
        $this->assertSame('restore', $permissions['restore']);
        $this->assertSame('exportCsv', $permissions['exportCsv']);
    }

    public function testCrudActionsHasCorrectVerbs(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $constant = $reflection->getConstant('CRUD_ACTIONS');

        $verbsPerMethod = [];
        foreach ($constant as $action) {
            $verbsPerMethod[$action['method']][] = $action['verb'];
        }

        $this->assertContains('get', $verbsPerMethod['search']);
        $this->assertContains('post', $verbsPerMethod['create']);
        $this->assertContains('get', $verbsPerMethod['read']);
        $this->assertContains('put', $verbsPerMethod['update']);
        $this->assertContains('patch', $verbsPerMethod['update']);
        $this->assertContains('post', $verbsPerMethod['update']);
        $this->assertContains('delete', $verbsPerMethod['delete']);
        $this->assertContains('delete', $verbsPerMethod['softDelete']);
        $this->assertContains('patch', $verbsPerMethod['restore']);
        $this->assertContains('put', $verbsPerMethod['restore']);
    }

    public function testCrudActionsHasCorrectPaths(): void
    {
        $reflection = new \ReflectionClass(Router::class);
        $constant = $reflection->getConstant('CRUD_ACTIONS');

        $pathsByMethod = [];
        foreach ($constant as $action) {
            $pathsByMethod[$action['method']][] = $action['path'];
        }

        $this->assertContains('', $pathsByMethod['search']);
        $this->assertContains('', $pathsByMethod['create']);
        $this->assertContains('/options', $pathsByMethod['options']);
        $this->assertContains('/export-csv', $pathsByMethod['exportCsv']);
        $this->assertContains('/{id:uuid}', $pathsByMethod['read']);
        $this->assertContains('/{id:uuid}', $pathsByMethod['update']);
        $this->assertContains('/{id:uuid}', $pathsByMethod['delete']);
        $this->assertContains('/{id:uuid}/soft', $pathsByMethod['softDelete']);
        $this->assertContains('/{id:uuid}/restore', $pathsByMethod['restore']);
    }

    public function testCrudSkipsMissingControllerMethods(): void
    {
        $previousApp = Facade::getFacadeApplication();

        $container = new Container();
        $container->instance('app', $container);
        $container->instance('router', new IlluminateRouter(new Dispatcher($container), $container));
        Facade::setFacadeApplication($container);

        Router::crud('items', RouterTestController::class, 'items');

        $routes = RouteFacade::getRoutes()->getRoutes();
        $actions = array_map(static fn ($route): string => $route->getActionName(), $routes);

        $this->assertContains(RouterTestController::class . '@search', $actions);
        $this->assertNotContains(RouterTestController::class . '@options', $actions);
        $this->assertNotContains(RouterTestController::class . '@create', $actions);
        $this->assertNotContains(RouterTestController::class . '@exportCsv', $actions);

        Facade::setFacadeApplication($previousApp);
    }
}
