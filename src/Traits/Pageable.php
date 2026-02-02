<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Provides pagination functionality for Eloquent queries.
 *
 * Supports standard pagination with configurable page size,
 * and an option to skip pagination entirely for full result sets.
 *
 * @example
 * ```
 * // Paginated results (default 40 per page)
 * GET /products
 *
 * // Custom page size
 * GET /products?perPage=20
 *
 * // Skip pagination (return all results)
 * GET /products?skipPagination=true
 * ```
 */
trait Pageable
{
    /**
     * The paginated/fetched data array.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $data = [];

    /**
     * Pagination metadata.
     *
     * Contains: current (page), perPage, pagesCount, count (total records)
     *
     * @var array{current?: int, perPage?: int, pagesCount?: int, count?: int}
     */
    protected array $paginationData = [];

    /**
     * Builds pagination for the query results.
     *
     * @param Builder $query The query builder instance
     * @param int $perPage Number of items per page (default: 40)
     * @param string $method Model method to call for serialization (default: 'toArray')
     */
    public function buildPagination(Builder $query, int $perPage = 40, string $method = 'toArray'): void
    {
        $this->data = [];
        $this->paginationData = [];

        $request = request();

        if ($request->query('skipPagination')) {
            $this->buildWithoutPagination($query, $method);
            return;
        }

        $this->buildWithPagination($query, $perPage, $method);
    }

    /**
     * Fetches all results without pagination.
     *
     * @param Builder $query The query builder instance
     * @param string $method Model method to call for serialization
     */
    private function buildWithoutPagination(Builder $query, string $method): void
    {
        $query->get()->each(function (Model $row) use ($method): void {
            $this->data[] = $row->$method();
        });
    }

    /**
     * Fetches paginated results with metadata.
     *
     * @param Builder $query The query builder instance
     * @param int $perPage Number of items per page
     * @param string $method Model method to call for serialization
     */
    private function buildWithPagination(Builder $query, int $perPage, string $method): void
    {
        $count = $query->count();
        $paginate = $query->paginate($perPage);

        $this->data = array_map(
            static fn(Model $model): array => $model->$method(),
            $paginate->items()
        );

        $this->paginationData = [
            'current' => $paginate->currentPage(),
            'perPage' => $paginate->perPage(),
            'pagesCount' => (int) ceil($count / $paginate->perPage()),
            'count' => $count,
        ];
    }
}
