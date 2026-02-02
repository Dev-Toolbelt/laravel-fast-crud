<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use Exception;
use BackedEnum;
use Illuminate\Http\Request;
use DevToolbelt\LaravelFastCrud\Traits\Pageable;
use DevToolbelt\LaravelFastCrud\Traits\Sortable;
use DevToolbelt\LaravelFastCrud\Traits\Limitable;
use DevToolbelt\LaravelFastCrud\Traits\Searchable;
use Illuminate\Support\Facades\Response;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportCsv
{
    use Searchable;
    use Sortable;
    use Limitable;
    use Pageable;

    protected string $csvFileName = 'export.csv';
    protected array $csvColumns = [];

    /**
     * @throws Exception
     */
    public function exportCsv(Request $request, string $method = 'toSoftArray'): StreamedResponse
    {
        $modelName = $this->modelClassName();

        /** @var Builder $query */
        $query = $modelName::query();

        $isAssociative = array_keys($this->csvColumns) !== range(0, count($this->csvColumns) - 1);
        $columnPaths = $isAssociative ? array_keys($this->csvColumns) : $this->csvColumns;

        $this->modifyExportCsvQuery($query);

        $this->processSearch($query, $request->get('filter', []));
        $this->processSort($query, $request->input('sort', ''));
        $this->buildPagination($query, (int)$request->input('perPage', 9_999_999), $method);

        return Response::stream(function () use ($columnPaths) {
            $handle = fopen('php://output', 'w');

            // Write CSV headers
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
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . date('Y-m-d_H-i-s_') . $this->csvFileName . '"',
            'Cache-Control' => 'max-age=0, no-cache, must-revalidate, proxy-revalidate',
            'Last-Modified' => gmdate("D, d M Y H:i:s") . ' GMT',
            'Content-Transfer-Encoding' => 'binary',
        ]);
    }

    /**
     * Get a nested value from an array using dot notation.
     * Returns empty string if the path doesn't exist.
     *
     * @param array $data The data array to search in
     * @param string $path The dot-notation path (e.g., 'deviceModel.supplier.name')
     * @return mixed The value at the path or empty string if not found
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

    protected function modifyExportCsvQuery(Builder $query): void
    {
    }

    /**
     * Write a CSV line with custom formatting that preserves special characters
     *
     * @param resource $handle
     * @param array $fields
     * @return void
     */
    private function writeCsvLine($handle, array $fields): void
    {
        $line = [];

        foreach ($fields as $field) {
            $field = (string) $field;

            if (str_contains($field, ',') || str_contains($field, "\n") || str_contains($field, '"')) {
                $line[] = '"' . $field . '"';
                continue;
            }

            $line[] = $field;
        }

        fwrite($handle, implode(',', $line) . "\n");
    }
}
