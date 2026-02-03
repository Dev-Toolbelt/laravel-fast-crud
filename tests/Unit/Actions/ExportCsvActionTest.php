<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Actions;

use BackedEnum;
use DevToolbelt\LaravelFastCrud\Actions\ExportCsv;
use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Mockery;
use Mockery\MockInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;
use DevToolbelt\LaravelFastCrud\Tests\Unit\Actions\Fixtures\TestStatus;

final class ExportCsvActionTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConfig();
    }

    private function createController(
        array $data = [],
        array $columns = [],
        string $fileName = 'test-export.csv'
    ): object {
        return new class ($data, $columns, $fileName) {
            use ExportCsv {
                exportCsv as traitExportCsv;
            }

            public ?Builder $modifiedQuery = null;
            public string $modelClass = '';
            private array $mockData;

            public function __construct(array $data, array $columns, string $fileName)
            {
                $this->mockData = $data ?: [['id' => 1, 'name' => 'Test']];
                $this->csvColumns = $columns;
                $this->csvFileName = $fileName;
            }

            public function setModelClass(string $class): void
            {
                $this->modelClass = $class;
            }

            public function setCsvColumns(array $columns): void
            {
                $this->csvColumns = $columns;
            }

            public function setCsvFileName(string $fileName): void
            {
                $this->csvFileName = $fileName;
            }

            protected function modelClassName(): string
            {
                return $this->modelClass;
            }

            protected function modifyExportCsvQuery(Builder $query): void
            {
                $this->modifiedQuery = $query;
            }

            public function buildPagination(Builder $query, int $perPage = 40, string $method = 'toArray'): void
            {
                $this->data = $this->mockData;
                $this->paginationData = [];
            }

            public function setMockData(array $data): void
            {
                $this->mockData = $data;
            }

            // Override exportCsv to avoid Response facade
            public function exportCsv(Request $request, ?string $method = null): StreamedResponse
            {
                $method = $method ?? 'toArray';
                $modelName = $this->modelClassName();
                $query = $modelName::query();
                $isAssociative = array_keys($this->csvColumns) !== range(0, count($this->csvColumns) - 1);
                $columnPaths = $isAssociative ? array_keys($this->csvColumns) : $this->csvColumns;

                $this->modifyExportCsvQuery($query);

                $this->processSearch($query, $request->get('filter', []));
                $this->processSort($query, $request->input('sort', ''));
                $this->buildPagination($query, (int) $request->input('perPage', 9_999_999), $method);

                $csvColumns = $this->csvColumns;
                $data = $this->data;
                $csvFileName = $this->csvFileName;

                return new StreamedResponse(function () use ($columnPaths, $csvColumns, $data): void {
                    $handle = fopen('php://output', 'w');

                    if (!empty($csvColumns)) {
                        $headers = array_values($csvColumns);
                        $this->writeCsvLinePublic($handle, $headers);
                    }

                    foreach ($data as $row) {
                        $csvRow = [];
                        foreach ($columnPaths as $columnPath) {
                            $value = $this->getNestedValuePublic($row, $columnPath);

                            if ($value instanceof BackedEnum) {
                                $value = $value->value;
                            }

                            $csvRow[] = $value;
                        }
                        $this->writeCsvLinePublic($handle, $csvRow);
                    }

                    fclose($handle);
                }, 200, [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="' . date('Y-m-d_H-i-s_') . $csvFileName . '"',
                    'Cache-Control' => 'max-age=0, no-cache, must-revalidate, proxy-revalidate',
                    'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
                    'Content-Transfer-Encoding' => 'binary',
                ]);
            }

            public function getNestedValuePublic(array $data, string $path): mixed
            {
                $keys = explode('.', $path);
                $value = $data;

                foreach ($keys as $key) {
                    if (!is_array($value) || !array_key_exists($key, $value)) {
                        return '';
                    }
                    $value = $value[$key];
                }

                return $value ?? '';
            }

            public function writeCsvLinePublic($handle, array $fields): void
            {
                $line = [];

                foreach ($fields as $field) {
                    $field = (string) $field;
                    $field = str_replace('"', "'", $field);

                    if (str_contains($field, ',') || str_contains($field, "\n")) {
                        $line[] = '"' . $field . '"';
                        continue;
                    }

                    $line[] = $field;
                }

                fwrite($handle, implode(',', $line) . "\n");
            }
        };
    }

    public function testExportCsvCallsModifyExportCsvQueryHook(): void
    {
        $this->controller = $this->createController(
            [['id' => 1, 'name' => 'Test']],
            ['id' => 'ID', 'name' => 'Name']
        );
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $this->controller->exportCsv($request);

        $this->assertNotNull($this->controller->modifiedQuery);
    }

    public function testExportCsvReturnsStreamedResponse(): void
    {
        $this->controller = $this->createController(
            [['id' => 1, 'name' => 'Test']],
            ['id' => 'ID', 'name' => 'Name']
        );
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testExportCsvHasCorrectContentType(): void
    {
        $this->controller = $this->createController(
            [['id' => 1, 'name' => 'Test']],
            ['id' => 'ID', 'name' => 'Name']
        );
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testExportCsvHasContentDispositionHeader(): void
    {
        $this->controller = $this->createController(fileName: 'products.csv');
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString('products.csv', $contentDisposition);
    }

    public function testExportCsvGeneratesCorrectCsvContent(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Product 1'],
            ['id' => 2, 'name' => 'Product 2'],
        ];
        $columns = ['id' => 'ID', 'name' => 'Name'];

        $this->controller = $this->createController($data, $columns);
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('ID,Name', $content);
        $this->assertStringContainsString('1,Product 1', $content);
        $this->assertStringContainsString('2,Product 2', $content);
    }

    public function testExportCsvHandlesNestedRelationships(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Product 1', 'category' => ['name' => 'Electronics']],
            ['id' => 2, 'name' => 'Product 2', 'category' => ['name' => 'Books']],
        ];
        $columns = [
            'id' => 'ID',
            'name' => 'Name',
            'category.name' => 'Category',
        ];

        $this->controller = $this->createController($data, $columns);
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('ID,Name,Category', $content);
        $this->assertStringContainsString('1,Product 1,Electronics', $content);
        $this->assertStringContainsString('2,Product 2,Books', $content);
    }

    public function testExportCsvHandlesDeeplyNestedRelationships(): void
    {
        $data = [
            [
                'id' => 1,
                'order' => [
                    'customer' => [
                        'name' => 'John Doe',
                    ],
                ],
            ],
        ];
        $columns = [
            'id' => 'ID',
            'order.customer.name' => 'Customer Name',
        ];

        $this->controller = $this->createController($data, $columns);
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('ID,Customer Name', $content);
        $this->assertStringContainsString('1,John Doe', $content);
    }

    public function testExportCsvHandlesMissingNestedValue(): void
    {
        $data = [
            ['id' => 1, 'category' => null],
            ['id' => 2, 'category' => ['name' => 'Books']],
        ];
        $columns = [
            'id' => 'ID',
            'category.name' => 'Category',
        ];

        $this->controller = $this->createController($data, $columns);
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $lines = explode("\n", trim($content));
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('1,', $lines[1]);
    }

    public function testExportCsvHandlesMissingNestedKeyInArray(): void
    {
        $data = [
            ['id' => 1, 'category' => []],
        ];
        $columns = [
            'id' => 'ID',
            'category.name' => 'Category',
        ];

        $this->controller = $this->createController($data, $columns);
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $lines = explode("\n", trim($content));
        $this->assertCount(2, $lines);
        $this->assertSame('1,', $lines[1]);
    }

    public function testExportCsvHandlesBackedEnum(): void
    {
        $data = [
            ['id' => 1, 'status' => TestStatus::ACTIVE],
            ['id' => 2, 'status' => TestStatus::INACTIVE],
        ];
        $columns = [
            'id' => 'ID',
            'status' => 'Status',
        ];

        $this->controller = $this->createController($data, $columns);
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('1,active', $content);
        $this->assertStringContainsString('2,inactive', $content);
    }

    public function testExportCsvHandlesIndexedColumns(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Product 1'],
        ];
        $columns = ['id', 'name'];

        $this->controller = $this->createController($data, $columns);
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('id,name', $content);
        $this->assertStringContainsString('1,Product 1', $content);
    }

    public function testExportCsvHandlesCommasInValues(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Product, with comma'],
        ];
        $columns = ['id' => 'ID', 'name' => 'Name'];

        $this->controller = $this->createController($data, $columns);
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('"Product, with comma"', $content);
    }

    public function testExportCsvHandlesNewlinesInValues(): void
    {
        $data = [
            ['id' => 1, 'description' => "Line 1\nLine 2"],
        ];
        $columns = ['id' => 'ID', 'description' => 'Description'];

        $this->controller = $this->createController($data, $columns);
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('"Line 1', $content);
    }

    public function testExportCsvHandlesDoubleQuotesInValues(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Product "Special"'],
        ];
        $columns = ['id' => 'ID', 'name' => 'Name'];

        $this->controller = $this->createController($data, $columns);
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Double quotes are replaced with single quotes
        $this->assertStringContainsString("Product 'Special'", $content);
    }

    public function testExportCsvWithEmptyColumnsHasNoContent(): void
    {
        $data = [
            ['id' => 1, 'name' => 'Product 1'],
        ];

        $this->controller = $this->createController($data, []);
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // When columns is empty array, no data rows are written
        $this->assertStringNotContainsString('Product 1', $content);
    }

    public function testExportCsvWithCustomMethod(): void
    {
        $this->controller = $this->createController(
            [['id' => 1, 'name' => 'Test']],
            ['id' => 'ID', 'name' => 'Name']
        );
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request, 'toSoftArray');

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testExportCsvHasCacheControlHeader(): void
    {
        $this->controller = $this->createController(
            [['id' => 1, 'name' => 'Test']],
            ['id' => 'ID', 'name' => 'Name']
        );
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
    }

    public function testExportCsvHandlesNullValues(): void
    {
        $data = [
            ['id' => 1, 'name' => null],
        ];
        $columns = ['id' => 'ID', 'name' => 'Name'];

        $this->controller = $this->createController($data, $columns);
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = $this->createRequestMock();

        $response = $this->controller->exportCsv($request);

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('1,', $content);
    }

    private function createRequestMock(
        int $perPage = 9999999,
        array $filters = [],
        string $sort = ''
    ): Request&MockInterface {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('input')->with('perPage', 9999999)->andReturn($perPage);
        $request->shouldReceive('get')->with('filter', [])->andReturn($filters);
        $request->shouldReceive('input')->with('sort', '')->andReturn($sort);

        return $request;
    }

    private function createBuilderMock(): Builder&MockInterface
    {
        $connectionMock = Mockery::mock('Illuminate\Database\Connection');
        $connectionMock->shouldReceive('getDriverName')->andReturn('mysql');

        $builderMock = Mockery::mock(Builder::class);
        $builderMock->shouldReceive('getConnection')->andReturn($connectionMock);
        $builderMock->shouldReceive('where')->andReturnSelf();
        $builderMock->shouldReceive('whereIn')->andReturnSelf();
        $builderMock->shouldReceive('orderBy')->andReturnSelf();
        $builderMock->shouldReceive('orderByDesc')->andReturnSelf();

        return $builderMock;
    }

    private function createMockModelClass(Builder $builderMock): string
    {
        $className = 'TestExportCsvModel' . uniqid();

        eval("
            namespace DevToolbelt\\LaravelFastCrud\\Tests\\Unit\\Actions;

            class {$className} extends \\Illuminate\\Database\\Eloquent\\Model {
                public static \$builderMock;

                public static function query() {
                    return self::\$builderMock;
                }
            }
        ");

        $fullClassName = "DevToolbelt\\LaravelFastCrud\\Tests\\Unit\\Actions\\{$className}";
        $fullClassName::$builderMock = $builderMock;

        return $fullClassName;
    }

    private function mockConfig(array $overrides = []): void
    {
        $defaults = [
            'devToolbelt.fast-crud.export_csv.method' => 'toArray',
        ];

        $config = array_merge($defaults, $overrides);

        if (!function_exists('DevToolbelt\LaravelFastCrud\Actions\config')) {
            eval('
                namespace DevToolbelt\LaravelFastCrud\Actions;

                function config($key, $default = null) {
                    static $config = [];
                    if (is_array($key)) {
                        $config = array_merge($config, $key);
                        return null;
                    }
                    return $config[$key] ?? $default;
                }
            ');
        }

        \DevToolbelt\LaravelFastCrud\Actions\config($config);
    }
}
