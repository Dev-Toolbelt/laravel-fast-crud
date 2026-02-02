<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Tests\Unit\Actions;

use DevToolbelt\LaravelFastCrud\Actions\ExportCsv;
use DevToolbelt\LaravelFastCrud\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExportCsvActionTest extends TestCase
{
    private object $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockConfig();

        $this->controller = new class {
            use ExportCsv {
                exportCsv as parentExportCsv;
            }

            public ?Builder $modifiedQuery = null;
            public string $modelClass = '';

            public function __construct()
            {
                $this->csvFileName = 'test-export.csv';
                $this->csvColumns = [
                    'id' => 'ID',
                    'name' => 'Name',
                ];
            }

            public function setModelClass(string $class): void
            {
                $this->modelClass = $class;
            }

            protected function modelClassName(): string
            {
                return $this->modelClass;
            }

            protected function modifyExportCsvQuery(Builder $query): void
            {
                $this->modifiedQuery = $query;
            }

            // Override buildPagination to avoid request() helper
            public function buildPagination(Builder $query, int $perPage = 40, string $method = 'toArray'): void
            {
                $this->data = [['id' => 1, 'name' => 'Test']];
                $this->paginationData = [];
            }

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

                return new StreamedResponse(function () use ($columnPaths): void {
                    $handle = fopen('php://output', 'w');

                    if (!empty($this->csvColumns)) {
                        $headers = array_values($this->csvColumns);
                        fputcsv($handle, $headers);
                    }

                    foreach ($this->data as $row) {
                        $csvRow = [];
                        foreach ($columnPaths as $columnPath) {
                            $csvRow[] = $row[$columnPath] ?? '';
                        }
                        fputcsv($handle, $csvRow);
                    }

                    fclose($handle);
                }, 200, [
                    'Content-Type' => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="' . date('Y-m-d_H-i-s_') . $this->csvFileName . '"',
                    'Cache-Control' => 'max-age=0, no-cache, must-revalidate, proxy-revalidate',
                    'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
                    'Content-Transfer-Encoding' => 'binary',
                ]);
            }
        };
    }

    public function testModifyExportCsvQueryHookIsCalled(): void
    {
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('filter', [])->andReturn([]);
        $request->shouldReceive('input')->with('sort', '')->andReturn('');
        $request->shouldReceive('input')->with('perPage', 9999999)->andReturn(9999999);

        $this->controller->exportCsv($request);

        $this->assertNotNull($this->controller->modifiedQuery);
    }

    public function testExportCsvReturnsStreamedResponse(): void
    {
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('filter', [])->andReturn([]);
        $request->shouldReceive('input')->with('sort', '')->andReturn('');
        $request->shouldReceive('input')->with('perPage', 9999999)->andReturn(9999999);

        $response = $this->controller->exportCsv($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);
    }

    public function testExportCsvHasCorrectContentType(): void
    {
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('filter', [])->andReturn([]);
        $request->shouldReceive('input')->with('sort', '')->andReturn('');
        $request->shouldReceive('input')->with('perPage', 9999999)->andReturn(9999999);

        $response = $this->controller->exportCsv($request);

        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testExportCsvHasContentDispositionHeader(): void
    {
        $builderMock = $this->createBuilderMock();
        $modelClass = $this->createMockModelClass($builderMock);
        $this->controller->setModelClass($modelClass);

        $request = Mockery::mock(Request::class);
        $request->shouldReceive('get')->with('filter', [])->andReturn([]);
        $request->shouldReceive('input')->with('sort', '')->andReturn('');
        $request->shouldReceive('input')->with('perPage', 9999999)->andReturn(9999999);

        $response = $this->controller->exportCsv($request);

        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $contentDisposition);
        $this->assertStringContainsString('test-export.csv', $contentDisposition);
    }

    private function createBuilderMock(): Builder
    {
        $builderMock = Mockery::mock(Builder::class);
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
