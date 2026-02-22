<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Integration\Actions;

use DevToolbelt\LaravelFastCrud\Tests\Integration\Fixtures\Product;
use DevToolbelt\LaravelFastCrud\Tests\Integration\Fixtures\ProductController;
use DevToolbelt\LaravelFastCrud\Tests\Integration\IntegrationTestCase;
use Illuminate\Http\Request;

final class SearchActionIntegrationTest extends IntegrationTestCase
{
    private ProductController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ProductController();
    }

    public function testSearchReturnsAllRecords(): void
    {
        Product::query()->create(['name' => 'Product 1', 'price' => 100]);
        Product::query()->create(['name' => 'Product 2', 'price' => 200]);
        Product::query()->create(['name' => 'Product 3', 'price' => 300]);

        $request = Request::create('/', 'GET');

        $response = $this->controller->search($request);
        $data = $this->getResponseData($response);

        $this->assertEquals('success', $data['status']);
        $this->assertCount(3, $data['data']);
    }

    public function testSearchWithPagination(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            Product::query()->create(['name' => "Product $i", 'price' => $i * 10]);
        }

        $request = Request::create('/', 'GET', ['perPage' => 10]);

        $response = $this->controller->search($request);
        $data = $this->getResponseData($response);

        $this->assertEquals('success', $data['status']);
        $this->assertCount(10, $data['data']);
        $this->assertEquals(1, $data['meta']['pagination']['current']);
        $this->assertEquals(10, $data['meta']['pagination']['perPage']);
        $this->assertEquals(5, $data['meta']['pagination']['pagesCount']);
        $this->assertEquals(50, $data['meta']['pagination']['count']);
    }

    public function testSearchWithSkipPagination(): void
    {
        for ($i = 1; $i <= 50; $i++) {
            Product::query()->create(['name' => "Product $i", 'price' => $i * 10]);
        }

        $request = Request::create('/', 'GET', ['skipPagination' => 'true']);
        $this->app->instance('request', $request);

        $response = $this->controller->search($request);
        $data = $this->getResponseData($response);

        $this->assertEquals('success', $data['status']);
        $this->assertCount(50, $data['data']);
        $this->assertEmpty($data['meta']['pagination']);
    }

    public function testSearchWithEqualFilter(): void
    {
        Product::query()->create(['name' => 'Product Active', 'price' => 100, 'status' => 'active']);
        Product::query()->create(['name' => 'Product Inactive', 'price' => 200, 'status' => 'inactive']);

        $request = Request::create('/', 'GET', [
            'filter' => ['status' => ['eq' => 'active']],
        ]);

        $response = $this->controller->search($request);
        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['data']);
        $this->assertEquals('Product Active', $data['data'][0]['name']);
    }

    public function testSearchWithLikeFilter(): void
    {
        Product::query()->create(['name' => 'Samsung Galaxy', 'price' => 100]);
        Product::query()->create(['name' => 'iPhone Pro', 'price' => 200]);
        Product::query()->create(['name' => 'Samsung Note', 'price' => 300]);

        $request = Request::create('/', 'GET', [
            'filter' => ['name' => ['like' => 'Samsung']],
        ]);

        $response = $this->controller->search($request);
        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['data']);
    }

    public function testSearchWithGreaterThanFilter(): void
    {
        Product::query()->create(['name' => 'Cheap', 'price' => 50]);
        Product::query()->create(['name' => 'Medium', 'price' => 100]);
        Product::query()->create(['name' => 'Expensive', 'price' => 200]);

        $request = Request::create('/', 'GET', [
            'filter' => ['price' => ['gt' => '100']],
        ]);

        $response = $this->controller->search($request);
        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['data']);
        $this->assertEquals('Expensive', $data['data'][0]['name']);
    }

    public function testSearchWithInFilter(): void
    {
        Product::query()->create(['name' => 'Active 1', 'price' => 100, 'status' => 'active']);
        Product::query()->create(['name' => 'Pending', 'price' => 200, 'status' => 'pending']);
        Product::query()->create(['name' => 'Inactive', 'price' => 300, 'status' => 'inactive']);

        $request = Request::create('/', 'GET', [
            'filter' => ['status' => ['in' => 'active,pending']],
        ]);

        $response = $this->controller->search($request);
        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['data']);
    }

    public function testSearchWithAscendingSort(): void
    {
        Product::query()->create(['name' => 'Zebra', 'price' => 100]);
        Product::query()->create(['name' => 'Apple', 'price' => 200]);
        Product::query()->create(['name' => 'Mango', 'price' => 300]);

        $request = Request::create('/', 'GET', ['sort' => 'name']);

        $response = $this->controller->search($request);
        $data = $this->getResponseData($response);

        $this->assertEquals('Apple', $data['data'][0]['name']);
        $this->assertEquals('Mango', $data['data'][1]['name']);
        $this->assertEquals('Zebra', $data['data'][2]['name']);
    }

    public function testSearchWithDescendingSort(): void
    {
        Product::query()->create(['name' => 'Zebra', 'price' => 100]);
        Product::query()->create(['name' => 'Apple', 'price' => 200]);
        Product::query()->create(['name' => 'Mango', 'price' => 300]);

        $request = Request::create('/', 'GET', ['sort' => '-name']);

        $response = $this->controller->search($request);
        $data = $this->getResponseData($response);

        $this->assertEquals('Zebra', $data['data'][0]['name']);
        $this->assertEquals('Mango', $data['data'][1]['name']);
        $this->assertEquals('Apple', $data['data'][2]['name']);
    }

    public function testSearchReturnsEmptyArrayWhenNoRecords(): void
    {
        $request = Request::create('/', 'GET');

        $response = $this->controller->search($request);
        $data = $this->getResponseData($response);

        $this->assertEquals('success', $data['status']);
        $this->assertEmpty($data['data']);
        $this->assertEquals(0, $data['meta']['pagination']['count']);
    }

    public function testSearchWithModifyFiltersHookAddsFilter(): void
    {
        Product::query()->create(['name' => 'Active Product', 'price' => 100, 'status' => 'active']);
        Product::query()->create(['name' => 'Inactive Product', 'price' => 200, 'status' => 'inactive']);
        Product::query()->create(['name' => 'Pending Product', 'price' => 300, 'status' => 'pending']);

        $controller = new class extends ProductController {
            protected function modifyFilters(array $filters): array
            {
                $filters['status'] = ['eq' => 'active'];
                return $filters;
            }
        };

        $request = Request::create('/', 'GET');

        $response = $controller->search($request);
        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['data']);
        $this->assertEquals('Active Product', $data['data'][0]['name']);
    }

    public function testSearchWithModifyFiltersHookRemovesFilter(): void
    {
        Product::query()->create(['name' => 'Active', 'price' => 100, 'status' => 'active']);
        Product::query()->create(['name' => 'Inactive', 'price' => 200, 'status' => 'inactive']);

        $controller = new class extends ProductController {
            protected function modifyFilters(array $filters): array
            {
                unset($filters['status']);
                return $filters;
            }
        };

        $request = Request::create('/', 'GET', [
            'filter' => ['status' => ['eq' => 'active']],
        ]);

        $response = $controller->search($request);
        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['data']);
    }

    public function testSearchWithModifyFiltersHookReplacesFilter(): void
    {
        Product::query()->create(['name' => 'Cheap', 'price' => 50, 'status' => 'active']);
        Product::query()->create(['name' => 'Medium', 'price' => 150, 'status' => 'active']);
        Product::query()->create(['name' => 'Expensive', 'price' => 500, 'status' => 'active']);

        $controller = new class extends ProductController {
            protected function modifyFilters(array $filters): array
            {
                $filters['price'] = ['gte' => '100'];
                return $filters;
            }
        };

        $request = Request::create('/', 'GET', [
            'filter' => ['price' => ['lt' => '100']],
        ]);

        $response = $controller->search($request);
        $data = $this->getResponseData($response);

        $this->assertCount(2, $data['data']);
    }

    public function testSearchExcludesSoftDeletedRecords(): void
    {
        Product::query()->create(['name' => 'Active', 'price' => 100]);
        Product::query()->create(['name' => 'Deleted', 'price' => 200, 'deleted_at' => now()]);

        $request = Request::create('/', 'GET');

        $response = $this->controller->search($request);
        $data = $this->getResponseData($response);

        $this->assertCount(1, $data['data']);
        $this->assertEquals('Active', $data['data'][0]['name']);
    }
}
