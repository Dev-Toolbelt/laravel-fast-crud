<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides the options (GET /options) action for CRUD controllers.
 *
 * Returns a list of label-value pairs suitable for populating select dropdowns
 * or autocomplete fields in frontend applications.
 *
 * Query parameters:
 * - label (required) - The column name to use as the display label
 * - value (optional) - The column name to use as the value (default: external_id)
 *
 * @method string modelClassName() Returns the Eloquent model class name
 * @method JsonResponse|ResponseInterface answerRequired(string $field) Returns required field error response
 * @method JsonResponse|ResponseInterface answerColumnNotFound(string $field) Returns column not found error response
 * @method JsonResponse|ResponseInterface answerSuccess(array $data, array $meta = []) Returns success response
 */
trait Options
{
    /**
     * Returns a list of label-value pairs for select dropdowns.
     *
     * @param Request $request The HTTP request with label and optional value parameters
     * @return JsonResponse|ResponseInterface JSON response with array of {label, value} objects
     *
     * @example
     * ```
     * GET /products/options?label=name
     * GET /categories/options?label=title&value=slug
     * ```
     */
    public function options(Request $request): JsonResponse|ResponseInterface
    {
        $defaultValue = config('devToolbelt.fast-crud.options.default_value', 'id');
        $value = $request->get('value', $defaultValue);
        $label = $request->get('label');

        if ($label === null) {
            return $this->answerRequired('label');
        }

        $modelName = $this->modelClassName();

        /** @var Model $model */
        $model = new $modelName();

        if (!$this->hasModelAttribute($model, $label)) {
            return $this->answerColumnNotFound('label');
        }

        $query = $model->newQuery()
            ->select([$value . ' as value', $label . ' as label'])
            ->orderBy($label, 'ASC');

        $this->modifyOptionsQuery($query);

        $records = $query->get()->toArray();

        $rows = array_map(static fn(array $record): array => [
            'label' => $record['label'],
            'value' => $record['value'],
        ], $records);

        $this->afterOptions($rows);

        return $this->answerSuccess($rows);
    }

    /**
     * Hook to modify the options query before execution.
     *
     * Override this method to add conditions, such as filtering
     * only active records or scoping to a specific user.
     *
     * @param Builder $query The query builder instance
     *
     * @example
     * ```php
     * protected function modifyOptionsQuery(Builder $query): void
     * {
     *     $query->where('is_active', true);
     * }
     * ```
     */
    protected function modifyOptionsQuery(Builder $query): void
    {
    }

    /**
     * Hook called after the options have been fetched and formatted.
     *
     * Override this method to perform post-fetch actions,
     * such as caching or modifying the results.
     *
     * @param array<int, array{label: mixed, value: mixed}> $rows The formatted options array
     */
    protected function afterOptions(array $rows): void
    {
    }
}
