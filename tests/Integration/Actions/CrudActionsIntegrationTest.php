<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Integration\Actions;

use DevToolbelt\LaravelFastCrud\Tests\Integration\Fixtures\Product;
use DevToolbelt\LaravelFastCrud\Tests\Integration\Fixtures\ProductController;
use DevToolbelt\LaravelFastCrud\Tests\Integration\IntegrationTestCase;
use Illuminate\Http\Request;

final class CrudActionsIntegrationTest extends IntegrationTestCase
{
    private ProductController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ProductController();
    }

    // ==================== CREATE ACTION ====================

    public function testCreateReturnsCreatedRecord(): void
    {
        $request = Request::create('/', 'POST', [
            'name' => 'New Product',
            'price' => 99.99,
            'status' => 'active',
        ]);

        $response = $this->controller->create($request);
        $data = $this->getResponseData($response);

        $this->assertEquals('success', $data['status']);
        $this->assertEquals('New Product', $data['data']['name']);

        $this->assertDatabaseHas('products', ['name' => 'New Product']);
    }

    public function testCreateReturnsValidationError(): void
    {
        $request = Request::create('/', 'POST', [
            'name' => '', // Required field is empty
            'price' => 'not-a-number',
        ]);

        $response = $this->controller->create($request);
        $data = $this->getResponseData($response);
        $statusCode = $this->getResponseStatusCode($response);

        $this->assertEquals('fail', $data['status']);
        $this->assertEquals(400, $statusCode);
    }

    public function testCreateReturnsEmptyPayloadError(): void
    {
        $request = Request::create('/', 'POST', []);

        $response = $this->controller->create($request);
        $data = $this->getResponseData($response);

        $this->assertEquals('fail', $data['status']);
        $this->assertEquals('emptyPayload', $data['data'][0]['error']);
    }

    // ==================== READ ACTION ====================

    public function testReadReturnsSingleRecord(): void
    {
        $product = Product::query()->create([
            'name' => 'Test Product',
            'price' => 150,
            'status' => 'active',
        ]);

        $response = $this->controller->read((string) $product->id);
        $data = $this->getResponseData($response);

        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Test Product', $data['data']['name']);
    }

    public function testReadReturnsNotFoundError(): void
    {
        $response = $this->controller->read('999');
        $data = $this->getResponseData($response);

        $this->assertEquals('fail', $data['status']);
        $this->assertEquals('recordNotFound', $data['data'][0]['error']);
    }

    // ==================== UPDATE ACTION ====================

    public function testUpdateModifiesRecord(): void
    {
        $product = Product::query()->create([
            'name' => 'Original Name',
            'price' => 100,
            'status' => 'active',
        ]);

        $request = Request::create('/', 'PUT', [
            'name' => 'Updated Name',
            'price' => 200,
        ]);

        $response = $this->controller->update($request, (string) $product->id);
        $data = $this->getResponseData($response);

        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Updated Name', $data['data']['name']);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
        ]);
    }

    public function testUpdateReturnsNotFoundError(): void
    {
        $request = Request::create('/', 'PUT', [
            'name' => 'Updated Name',
        ]);

        $response = $this->controller->update($request, '999');
        $data = $this->getResponseData($response);

        $this->assertEquals('fail', $data['status']);
        $this->assertEquals('recordNotFound', $data['data'][0]['error']);
    }

    public function testUpdateReturnsValidationError(): void
    {
        $product = Product::query()->create([
            'name' => 'Original',
            'price' => 100,
            'status' => 'active',
        ]);

        $request = Request::create('/', 'PUT', [
            'price' => 'not-a-number',
        ]);

        $response = $this->controller->update($request, (string) $product->id);
        $data = $this->getResponseData($response);
        $statusCode = $this->getResponseStatusCode($response);

        $this->assertEquals('fail', $data['status']);
        $this->assertEquals(400, $statusCode);
    }

    // ==================== DELETE ACTION ====================

    public function testDeleteDelegatesToSoftDeleteWhenModelUsesSoftDeletes(): void
    {
        $product = Product::query()->create([
            'name' => 'To Delete',
            'price' => 100,
            'status' => 'active',
        ]);

        $response = $this->controller->delete((string) $product->id);
        $data = $this->getResponseData($response);

        $this->assertEquals('success', $data['status']);
        $this->assertNull($data['data']);

        // Since Product uses SoftDeletes, delete() delegates to softDelete()
        // The record should be soft deleted, not hard deleted
        $this->assertNotNull($product->fresh()->deleted_at);
    }

    public function testDeleteReturnsNotFoundError(): void
    {
        $response = $this->controller->delete('999');
        $data = $this->getResponseData($response);

        $this->assertEquals('fail', $data['status']);
        $this->assertEquals('recordNotFound', $data['data'][0]['error']);
    }

    // ==================== SOFT DELETE ACTION ====================

    public function testSoftDeleteMarksRecordAsDeleted(): void
    {
        $product = Product::query()->create([
            'name' => 'To Soft Delete',
            'price' => 100,
            'status' => 'active',
        ]);

        $response = $this->controller->softDelete((string) $product->id);
        $data = $this->getResponseData($response);

        $this->assertEquals('success', $data['status']);
        $this->assertNull($data['data']);

        $product->refresh();
        $this->assertNotNull($product->deleted_at);
    }

    public function testSoftDeleteReturnsNotFoundError(): void
    {
        $response = $this->controller->softDelete('999');
        $data = $this->getResponseData($response);

        $this->assertEquals('fail', $data['status']);
        $this->assertEquals('recordNotFound', $data['data'][0]['error']);
    }

    public function testSoftDeleteDoesNotDeleteAlreadySoftDeleted(): void
    {
        $product = Product::query()->create([
            'name' => 'Already Deleted',
            'price' => 100,
            'status' => 'active',
            'deleted_at' => now(),
            'deleted_by' => 1,
        ]);

        $response = $this->controller->softDelete((string) $product->id);
        $data = $this->getResponseData($response);

        $this->assertEquals('fail', $data['status']);
        $this->assertEquals('recordNotFound', $data['data'][0]['error']);
    }

    // ==================== RESTORE ACTION ====================

    public function testRestoreRecoversSoftDeletedRecord(): void
    {
        $product = Product::query()->create([
            'name' => 'Deleted Product',
            'price' => 100,
            'status' => 'active',
        ]);

        // Soft delete the product first
        $product->delete();
        $this->assertNotNull($product->fresh()->deleted_at);

        $response = $this->controller->restore((string) $product->id);
        $data = $this->getResponseData($response);

        $this->assertEquals('success', $data['status']);
        $this->assertNull($product->fresh()->deleted_at);
    }

    public function testRestoreReturnsNotFoundForNonDeletedRecord(): void
    {
        $product = Product::query()->create([
            'name' => 'Active Product',
            'price' => 100,
            'status' => 'active',
        ]);

        $response = $this->controller->restore((string) $product->id);
        $data = $this->getResponseData($response);

        $this->assertEquals('fail', $data['status']);
        $this->assertEquals('recordNotFound', $data['data'][0]['error']);
    }

    // ==================== OPTIONS ACTION ====================

    public function testOptionsReturnsLabelValuePairs(): void
    {
        Product::query()->create(['name' => 'Product A', 'price' => 100, 'status' => 'active']);
        Product::query()->create(['name' => 'Product B', 'price' => 200, 'status' => 'active']);

        $request = Request::create('/options', 'GET', [
            'label' => 'name',
        ]);

        $response = $this->controller->options($request);
        $data = $this->getResponseData($response);

        $this->assertEquals('success', $data['status']);
        $this->assertCount(2, $data['data']);
        $this->assertArrayHasKey('label', $data['data'][0]);
        $this->assertArrayHasKey('value', $data['data'][0]);
    }

    public function testOptionsWithCustomValueField(): void
    {
        Product::query()->create(['name' => 'Product A', 'price' => 100, 'status' => 'active']);

        $request = Request::create('/options', 'GET', [
            'label' => 'name',
            'value' => 'status',
        ]);

        $response = $this->controller->options($request);
        $data = $this->getResponseData($response);

        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Product A', $data['data'][0]['label']);
        $this->assertEquals('active', $data['data'][0]['value']);
    }

    public function testOptionsReturnsEmptyWhenNoRecords(): void
    {
        $request = Request::create('/options', 'GET', [
            'label' => 'name',
        ]);

        $response = $this->controller->options($request);
        $data = $this->getResponseData($response);

        $this->assertEquals('success', $data['status']);
        $this->assertEmpty($data['data']);
    }
}
