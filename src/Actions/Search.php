<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use DevToolbelt\LaravelFastCrud\Traits\Limitable;
use DevToolbelt\LaravelFastCrud\Traits\Pageable;
use DevToolbelt\LaravelFastCrud\Traits\Searchable;
use DevToolbelt\LaravelFastCrud\Traits\Sortable;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides the search/list (GET collection) action for CRUD controllers.
 *
 * Retrieves a paginated, filtered, and sorted list of model instances.
 * Supports the following query parameters:
 * - filter[column][operator]=value - Filter records (see SearchOperator enum)
 * - sort=column,-desc_column - Sort by columns (prefix with - for DESC)
 * - perPage=N - Items per page (default: 40)
 * - skipPagination=true - Return all records without pagination
 *
 * @method string modelClassName() Returns the Eloquent model class name
 * @method JsonResponse|ResponseInterface answerSuccess(array $data, array $meta = []) Returns success response
 *
 * @property array $data Paginated records data (from Pageable trait)
 * @property array $paginationData Pagination metadata (from Pageable trait)
 */
trait Search
{
    use Searchable;
    use Sortable;
    use Limitable;
    use Pageable;

    /**
     * Searches and returns a paginated list of records.
     *
     * @param Request $request The HTTP request with filter, sort, and pagination parameters
     * @param string $method The model method to call for serialization (default: 'toSoftArray')
     * @return JsonResponse|ResponseInterface JSON response with records and pagination metadata
     *
     * @throws Exception When an invalid search operator is provided
     *
     * @example
     * ```
     * GET /products?filter[name][like]=Samsung&filter[price][gte]=100&sort=-created_at&perPage=20
     * ```
     */
    public function search(Request $request, string $method = 'toArray'): JsonResponse|ResponseInterface
    {
        $modelName = $this->modelClassName();
        $query = $modelName::query();

        $this->modifySearchQuery($query);
        $this->processSearch($query, $request->get('filter', []));
        $this->processSort($query, $request->input('sort', ''));
        $this->buildPagination($query, (int) $request->input('perPage', 40), $method);

        return $this->answerSuccess($this->data, meta: [
            'pagination' => $this->paginationData
        ]);
    }

    /**
     * Hook to modify the search query before filters and sorting are applied.
     *
     * Override this method to add base conditions, eager loading,
     * or scopes that should always be applied to search queries.
     *
     * @param Builder $query The query builder instance
     *
     * @example
     * ```php
     * protected function modifySearchQuery(Builder $query): void
     * {
     *     $query->with(['category', 'tags'])
     *           ->where('is_active', true);
     * }
     * ```
     */
    protected function modifySearchQuery(Builder $query): void
    {
    }
}
