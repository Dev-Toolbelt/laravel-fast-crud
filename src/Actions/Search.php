<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use DevToolbelt\Enums\Http\HttpStatusCode;
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
 * - perPage=N - Items per page (default from config or 40)
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
     * Filter key used for full-text-like term search.
     */
    protected string $termFieldName = 'term';

    /**
     * Model fields used when applying term search.
     *
     * @var array<int, string>
     */
    protected array $termFields = [];

    /**
     * Searches and returns a paginated list of records.
     *
     * @param Request $request The HTTP request with filter, sort, and pagination parameters
     * @param string|null $method Model serialization method (default from config or 'toArray')
     * @return JsonResponse|ResponseInterface JSON response with records and pagination metadata
     *
     * @throws Exception When an invalid search operator is provided
     *
     * @example
     * ```
     * GET /products?filter[name][like]=Samsung&filter[price][gte]=100&sort=-created_at&perPage=20
     * ```
     */
    public function search(Request $request, ?string $method = null): JsonResponse|ResponseInterface
    {
        $method = $method ?? config('devToolbelt.fast-crud.search.method', 'toArray');
        $httpStatus = HttpStatusCode::from(
            config('devToolbelt.fast-crud.search.http_status', HttpStatusCode::OK->value)
        );
        $defaultPerPage = config('devToolbelt.fast-crud.search.per_page', 40);
        $perPage = (int) $request->input('perPage', $defaultPerPage);
        $modelName = $this->modelClassName();
        $query = $modelName::query();

        $this->modifySearchQuery($query);
        $filters = $this->modifyFilters($request->get('filter', []));
        $this->processSearch($query, $filters);
        $this->processSort($query, $request->input('sort', ''));

        $this->buildPagination($query, $perPage, $method);
        $this->afterSearch($this->data);

        return $this->answerSuccess(data: $this->data, code: $httpStatus, meta: [
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

    /**
     * Hook to modify the filters before they are applied to the query.
     *
     * Override this method to add, remove, or transform filters
     * before they are processed by the search engine.
     *
     * @param array<string, mixed> $filters The original filters from the request
     * @return array<string, mixed> The modified filters
     *
     * @example
     * ```php
     * protected function modifyFilters(array $filters): array
     * {
     *     $filters['is_active'] = ['eq' => true];
     *     unset($filters['internal_field']);
     *     return $filters;
     * }
     * ```
     */
    protected function modifyFilters(array $filters): array
    {
        return $filters;
    }

    /**
     * Hook called after the search results have been fetched.
     *
     * Override this method to perform post-search actions,
     * such as caching, analytics, or result transformation.
     *
     * @param array<int, array<string, mixed>> $data The search results array
     */
    protected function afterSearch(array $data): void
    {
    }
}
