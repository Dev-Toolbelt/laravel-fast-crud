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
 *
 * Lifecycle:
 * 1. UUID validation (if configured)
 * 2. beforeUpdateFill() - Transform or add data before validation
 * 3. updateValidateRules() - Validate data using Laravel validation rules
 * 4. modifyUpdateQuery() - Customize the query to find the record
 * 5. beforeUpdate() - Final modifications before persistence
 * 6. Model::update() - Persist the changes
 * 7. afterUpdate() - Post-update operations (events, cache, etc.)
 *
 * @method string modelClassName() Returns the Eloquent model class name
 * @method JsonResponse|ResponseInterface answerInvalidUuid() Returns invalid UUID error response
 * @method JsonResponse|ResponseInterface answerEmptyPayload() Returns empty payload error response
 * @method JsonResponse|ResponseInterface answerRecordNotFound() Returns not found error response
 * @method JsonResponse|ResponseInterface answerSuccess(array $data, array $meta = []) Returns success response
 * @method JsonResponse|ResponseInterface runValidation(array $data, array $rules) Validates data and returns error response if fails
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
        $method = $method ?? config('devToolbelt.fast-crud.update.method', 'toArray');
        $findField = config('devToolbelt.fast-crud.update.find_field')
            ?? config('devToolbelt.fast-crud.global.find_field', 'id');

        $isUuid = config('devToolbelt.fast-crud.update.find_field_is_uuid')
            ?? config('devToolbelt.fast-crud.global.find_field_is_uuid', false);

        if ($isUuid && !Str::isUuid($id)) {
            return $this->answerInvalidUuid();
        }

        $data = $request->post();

        if (empty($data)) {
            return $this->answerEmptyPayload();
        }

        $this->beforeUpdateFill($data);

        $validationResponse = $this->runValidation($data, $this->updateValidateRules());

        if ($validationResponse !== null) {
            return $validationResponse;
        }

        $modelName = $this->modelClassName();
        $query = $modelName::query()->where($findField, $id);
        $this->modifyUpdateQuery($query);

        /** @var Model|null $record */
        $record = $query->first();

        if ($record === null) {
            return $this->answerRecordNotFound();
        }

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

    /**
     * Define validation rules for the update action.
     *
     * Override this method to return Laravel validation rules.
     * If rules are defined, the data will be validated after beforeUpdateFill()
     * and before finding the record.
     *
     * For partial updates, use the 'sometimes' rule to only validate fields that are present.
     *
     * @return array<string, mixed> Laravel validation rules
     *
     * @example
     * ```php
     * protected function updateValidateRules(): array
     * {
     *     return [
     *         'name' => ['sometimes', 'string', 'max:255'],
     *         'email' => ['sometimes', 'email', 'unique:users,email,' . request()->route('id')],
     *         'price' => ['sometimes', 'numeric', 'min:0'],
     *     ];
     * }
     * ```
     */
    protected function updateValidateRules(): array
    {
        return [];
    }
}
