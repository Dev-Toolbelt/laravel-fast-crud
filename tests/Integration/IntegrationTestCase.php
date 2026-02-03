<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Integration;

use DevToolbelt\LaravelFastCrud\FastCrudServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Psr\Http\Message\ResponseInterface;

abstract class IntegrationTestCase extends OrchestraTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('products')) {
            $this->setUpDatabase();
        }
    }

    protected function getPackageProviders($app): array
    {
        return [
            FastCrudServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');

        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('devToolbelt.fast-crud', [
            'global' => [
                'find_field' => 'id',
                'find_field_is_uuid' => false,
            ],
            'create' => ['method' => 'toArray'],
            'read' => ['method' => 'toArray'],
            'update' => ['method' => 'toArray'],
            'delete' => [],
            'soft_delete' => [
                'deleted_at_field' => 'deleted_at',
                'deleted_by_field' => 'deleted_by',
            ],
            'restore' => ['method' => 'toArray'],
            'search' => ['method' => 'toArray', 'per_page' => 40],
            'options' => ['default_value' => 'id'],
            'export_csv' => ['method' => 'toArray'],
        ]);
    }

    protected function setUpDatabase(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status')->default('active');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    /**
     * Extract response content and decode JSON from either JsonResponse or PSR-7 Response.
     */
    protected function getResponseData(JsonResponse|ResponseInterface $response): array
    {
        if ($response instanceof JsonResponse) {
            return json_decode($response->getContent(), true) ?? [];
        }

        // PSR-7 Response
        $body = $response->getBody();
        $body->rewind();
        $content = $body->getContents();

        return json_decode($content, true) ?? [];
    }

    /**
     * Get response status code from either JsonResponse or PSR-7 Response.
     */
    protected function getResponseStatusCode(JsonResponse|ResponseInterface $response): int
    {
        if ($response instanceof JsonResponse) {
            return $response->getStatusCode();
        }

        return $response->getStatusCode();
    }
}
