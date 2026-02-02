<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides the update (PUT/PATCH/POST by ID) action for CRUD controllers.
 *
 * Updates an existing model instance with the request data.
 * Executes the lifecycle: beforeUpdateFill() -> beforeUpdate() -> update() -> afterUpdate()
 *
 * @method string modelClassName() Returns the Eloquent model class name
 * @method JsonResponse|ResponseInterface answerInvalidUuid() Returns invalid UUID error response
 * @method JsonResponse|ResponseInterface answerEmptyPayload() Returns empty payload error response
 * @method JsonResponse|ResponseInterface answerRecordNotFound() Returns not found error response
 * @method JsonResponse|ResponseInterface answerSuccess(array $data, array $meta = []) Returns success response
 */
trait Update
{
    /**
     * Updates an existing record with the request data.
     *
     * @param Request $request The HTTP request containing the updated data
     * @param string $id The identifier value of the record to update
     * @param string|null $method Model serialization method (default from config or 'toArray')
     * @return JsonResponse|ResponseInterface JSON response with the updated record or error response
     */
    public function update(Request $request, string $id, ?string $method = null): JsonResponse|ResponseInterface
    {
        $method = $method ?? config('devToolbelt.fast_crud.update.method', 'toArray');
        $findField = config('devToolbelt.fast_crud.update.find_field')
            ?? config('devToolbelt.fast_crud.global.find_field', 'id');

        $isUuid = config('devToolbelt.fast_crud.update.find_field_is_uuid')
            ?? config('devToolbelt.fast_crud.global.find_field_is_uuid', false);

        if ($isUuid && !Str::isUuid($id)) {
            return $this->answerInvalidUuid();
        }

        $data = $request->post();

        if (empty($data)) {
            return $this->answerEmptyPayload();
        }

        $modelName = $this->modelClassName();
        $query = $modelName::query()->where($findField, $id);
        $this->modifyUpdateQuery($query);

        /** @var Model|null $record */
        $record = $query->first();

        if ($record === null) {
            return $this->answerRecordNotFound();
        }

        $this->beforeUpdateFill($data);
        $this->beforeUpdate($record, $data);
        $record->update($data);
        $this->afterUpdate($record);

        return $this->answerSuccess($record->{$method}());
    }

    /**
     * Hook to modify the update query before fetching the record.
     *
     * Override this method to add eager loading, additional conditions,
     * or scopes to the query.
     *
     * @param Builder $query The query builder instance
     *
     * @example
     * ```php
     * protected function modifyUpdateQuery(Builder $query): void
     * {
     *     $query->where('user_id', auth()->id());
     * }
     * ```
     */
    protected function modifyUpdateQuery(Builder $query): void
    {
    }

    /**
     * Hook called before filling the model with request data during update.
     *
     * Override this method to transform, validate, or add additional data
     * before the model is filled.
     *
     * @param array<string, mixed> $data Request data to be filled into the model (passed by reference)
     */
    protected function beforeUpdateFill(array &$data): void
    {
    }

    /**
     * Hook called before the model is updated.
     *
     * Override this method for additional validations, setting computed fields,
     * or any logic that needs access to the record and data before persistence.
     *
     * @param Model $record The model instance about to be updated
     * @param array<string, mixed> $data The data to be used for update (passed by reference)
     */
    protected function beforeUpdate(Model $record, array &$data): void
    {
    }

    /**
     * Hook called after the model has been successfully updated.
     *
     * Override this method for post-update operations like dispatching events,
     * updating related models, or logging.
     *
     * @param Model $record The updated model instance
     */
    protected function afterUpdate(Model $record): void
    {
    }
}
