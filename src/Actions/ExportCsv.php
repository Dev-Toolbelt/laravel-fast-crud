<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use BackedEnum;
use DevToolbelt\LaravelFastCrud\Traits\Limitable;
use DevToolbelt\LaravelFastCrud\Traits\Pageable;
use DevToolbelt\LaravelFastCrud\Traits\Searchable;
use DevToolbelt\LaravelFastCrud\Traits\Sortable;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Provides the CSV export (GET /export-csv) action for CRUD controllers.
 *
 * Exports filtered and sorted records to a downloadable CSV file.
 * Supports the same filter and sort parameters as the Search action.
 *
 * Configure the export by setting:
 * - $csvFileName: The output filename (default: 'export.csv')
 * - $csvColumns: Column mapping array (key = model path, value = CSV header)
 *
 * @method string modelClassName() Returns the Eloquent model class name
 *
 * @property array $data Paginated records data (from Pageable trait)
 *
 * @example
 * ```php
 * class ProductController extends CrudController
 * {
 *     protected string $csvFileName = 'products.csv';
 *     protected array $csvColumns = [
 *         'name' => 'Product Name',
 *         'category.name' => 'Category',
 *         'price' => 'Price',
 *         'created_at' => 'Created At',
 *     ];
 * }
 * ```
 */
trait ExportCsv
{
    use Searchable;
    use Sortable;
    use Limitable;
    use Pageable;

    /**
     * The filename for the exported CSV file.
     * Will be prefixed with the current timestamp (Y-m-d_H-i-s_).
     */
    protected string $csvFileName = 'export.csv';

    /**
     * Column mapping for CSV export.
     *
     * Can be either:
     * - Associative array: ['model.path' => 'CSV Header'] - maps model attributes to custom headers
     * - Indexed array: ['column1', 'column2'] - uses column names as headers
     *
     * Supports dot notation for nested relationships (e.g., 'category.name', 'user.profile.avatar').
     *
     * @var array<string, string>|array<int, string>
     */
    protected array $csvColumns = [];

    /**
     * Exports records to a CSV file download.
     *
     * @param Request $request The HTTP request with optional filter and sort parameters
     * @param string|null $method Model serialization method (default from config or 'toArray')
     * @return StreamedResponse Streamed CSV file download response
     *
     * @throws Exception When an invalid search operator is provided
     */
    public function exportCsv(Request $request, ?string $method = null): StreamedResponse
    {
        $method = $method ?? config('devToolbelt.fast-crud.export_csv.method', 'toArray');
        $modelName = $this->modelClassName();
        $query = $modelName::query();
        $isAssociative = array_keys($this->csvColumns) !== range(0, count($this->csvColumns) - 1);
        $columnPaths = $isAssociative ? array_keys($this->csvColumns) : $this->csvColumns;

        $this->modifyExportCsvQuery($query);

        $this->processSearch($query, $request->get('filter', []));
        $this->processSort($query, $request->input('sort', ''));
        $this->buildPagination($query, (int) $request->input('perPage', 9_999_999), $method);

        return Response::stream(function () use ($columnPaths): void {
            $handle = fopen('php://output', 'w');

            if (!empty($this->csvColumns)) {
                $headers = array_values($this->csvColumns);
                $this->writeCsvLine($handle, $headers);
            }

            foreach ($this->data as $row) {
                $csvRow = [];
                foreach ($columnPaths as $columnPath) {
                    $value = $this->getNestedValue($row, $columnPath);

                    if ($value instanceof BackedEnum) {
                        $value = $value->value;
                    }

                    $csvRow[] = $value;
                }
                $this->writeCsvLine($handle, $csvRow);
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

    /**
     * Gets a nested value from an array using dot notation.
     *
     * @param array<string, mixed> $data The data array to search in
     * @param string $path The dot-notation path (e.g., 'category.name', 'user.profile.avatar')
     * @return mixed The value at the path, or empty string if not found
     */
    private function getNestedValue(array $data, string $path): mixed
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

    /**
     * Hook to modify the export query before filters and sorting are applied.
     *
     * Override this method to add base conditions or eager loading
     * for the export query.
     *
     * @param Builder $query The query builder instance
     *
     * @example
     * ```php
     * protected function modifyExportCsvQuery(Builder $query): void
     * {
     *     $query->with(['category', 'supplier'])
     *           ->where('is_exportable', true);
     * }
     * ```
     */
    protected function modifyExportCsvQuery(Builder $query): void
    {
    }

    /**
     * Writes a CSV line with proper escaping for special characters.
     *
     * Handles commas and newlines by wrapping in quotes.
     * Double quotes are replaced with single quotes for cleaner output.
     *
     * @param resource $handle The file handle to write to
     * @param array<int, mixed> $fields The fields to write
     */
    private function writeCsvLine($handle, array $fields): void
    {
        $line = [];

        foreach ($fields as $field) {
            $field = (string) $field;

            // Replace double quotes with single quotes for cleaner output
            $field = str_replace('"', "'", $field);

            if (str_contains($field, ',') || str_contains($field, "\n")) {
                $line[] = '"' . $field . '"';
                continue;
            }

            $line[] = $field;
        }

        fwrite($handle, implode(',', $line) . "\n");
    }
}
