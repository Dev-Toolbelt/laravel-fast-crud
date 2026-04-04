<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use DevToolbelt\Enums\Http\HttpStatusCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
 * @method JsonResponse|ResponseInterface answerRequired(string $field, HttpStatusCode $code) Required field error
 * @method JsonResponse|ResponseInterface answerColumnNotFound(string $field, HttpStatusCode $code) Column not found
 * @method JsonResponse|ResponseInterface answerSuccess(mixed $data, HttpStatusCode $code, array $meta = [])
 *         Returns success response
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
        $httpStatus = HttpStatusCode::from(
            config('devToolbelt.fast-crud.options.http_status', HttpStatusCode::OK->value)
        );

        $validationHttpStatus = HttpStatusCode::from(
            config('devToolbelt.fast-crud.global.validation.http_status', HttpStatusCode::BAD_REQUEST->value)
        );

        $defaultValue = config('devToolbelt.fast-crud.options.default_value', 'id');
        $deletedAtField = config('devToolbelt.fast-crud.soft_delete.deleted_at_field', 'deleted_at');

        $value = $request->get('value', $defaultValue);
        $label = $request->get('label');

        if ($label === null) {
            return $this->answerRequired('label', $validationHttpStatus);
        }

        $modelName = $this->modelClassName();

        /** @var Model $model */
        $model = new $modelName();

        if (!$this->hasModelAttribute($model, $label)) {
            return $this->answerColumnNotFound($label, $validationHttpStatus);
        }

        $table = $model->getTable();
        $connection = $model->getConnectionName();

        $query = DB::connection($connection)
            ->table($table)
            ->select([$value . ' as value', $label . ' as label'])
            ->orderBy($label, 'ASC');

        if ($this->hasModelAttribute($model, $deletedAtField)) {
            $query->whereNull($deletedAtField);
        }

        $this->modifyOptionsQuery($query);

        $rows = $query->get()
            ->map(static fn(object $record): array => [
                'label' => $record->label,
                'value' => $record->value,
            ])
            ->all();

        $this->afterOptions($rows);

        return $this->answerSuccess(data: $rows, code: $httpStatus);
    }

    /**
     * Hook to modify the options query before execution.
     *
     * Override this method to add conditions, such as filtering
     * only active records or scoping to a specific user.
     *
     * @param Builder|QueryBuilder $query The query builder instance
     *
     * @example
     * ```php
     * protected function modifyOptionsQuery(Builder|QueryBuilder $query): void
     * {
     *     $query->where('is_active', true);
     * }
     * ```
     */
    protected function modifyOptionsQuery(Builder|QueryBuilder $query): void
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
