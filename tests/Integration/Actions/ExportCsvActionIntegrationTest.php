<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Integration\Actions;

use DevToolbelt\LaravelFastCrud\Tests\Integration\Fixtures\Product;
use DevToolbelt\LaravelFastCrud\Tests\Integration\Fixtures\ProductController;
use DevToolbelt\LaravelFastCrud\Tests\Integration\IntegrationTestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportCsvActionIntegrationTest extends IntegrationTestCase
{
    private ProductController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ProductController();
    }

    public function testExportCsvReturnsStreamedResponse(): void
    {
        Product::query()->create(['name' => 'Product 1', 'price' => 100]);

        $request = Request::create('/export-csv', 'GET');

        $response = $this->controller->exportCsv($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testExportCsvHasCorrectHeaders(): void
    {
        Product::query()->create(['name' => 'Product 1', 'price' => 100]);

        $request = Request::create('/export-csv', 'GET');

        $response = $this->controller->exportCsv($request);

        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('products.csv', $response->headers->get('Content-Disposition'));
    }

    public function testExportCsvGeneratesCorrectContent(): void
    {
        Product::query()->create(['name' => 'Product 1', 'price' => 100.50, 'status' => 'active']);
        Product::query()->create(['name' => 'Product 2', 'price' => 200.75, 'status' => 'pending']);

        $request = Request::create('/export-csv', 'GET');

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('ID,Name,Price,Status', $content);
        $this->assertStringContainsString('Product 1', $content);
        $this->assertStringContainsString('Product 2', $content);
        $this->assertStringContainsString('100.50', $content);
        $this->assertStringContainsString('200.75', $content);
    }

    public function testExportCsvWithFilters(): void
    {
        Product::query()->create(['name' => 'Active Product', 'price' => 100, 'status' => 'active']);
        Product::query()->create(['name' => 'Inactive Product', 'price' => 200, 'status' => 'inactive']);

        $request = Request::create('/export-csv', 'GET', [
            'filter' => ['status' => ['eq' => 'active']],
        ]);

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('Active Product', $content);
        $this->assertStringNotContainsString('Inactive Product', $content);
    }

    public function testExportCsvWithSorting(): void
    {
        Product::query()->create(['name' => 'Zebra', 'price' => 300, 'status' => 'active']);
        Product::query()->create(['name' => 'Apple', 'price' => 100, 'status' => 'active']);
        Product::query()->create(['name' => 'Mango', 'price' => 200, 'status' => 'active']);

        $request = Request::create('/export-csv', 'GET', ['sort' => 'name']);

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $lines = explode("\n", trim($content));
        // First line is header, then data rows
        $this->assertStringContainsString('Apple', $lines[1]);
        $this->assertStringContainsString('Mango', $lines[2]);
        $this->assertStringContainsString('Zebra', $lines[3]);
    }

    public function testExportCsvExportsAllRecordsWithoutPagination(): void
    {
        for ($i = 1; $i <= 100; $i++) {
            Product::query()->create(['name' => "Product $i", 'price' => $i * 10, 'status' => 'active']);
        }

        $request = Request::create('/export-csv', 'GET');

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $lines = explode("\n", trim($content));
        // 1 header + 100 data rows
        $this->assertCount(101, $lines);
    }

    public function testExportCsvHandlesSpecialCharacters(): void
    {
        Product::query()->create(['name' => 'Product, with comma', 'price' => 100, 'status' => 'active']);

        $request = Request::create('/export-csv', 'GET');

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Values with commas should be quoted
        $this->assertStringContainsString('"Product, with comma"', $content);
    }

    public function testExportCsvHandlesEmptyDatabase(): void
    {
        $request = Request::create('/export-csv', 'GET');

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Should only have header row
        $lines = explode("\n", trim($content));
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('ID,Name,Price,Status', $lines[0]);
    }
}
