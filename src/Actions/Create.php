<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use DevToolbelt\Enums\Http\HttpStatusCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides the creation (POST) action for CRUD controllers.
 *
 * Creates a new model instance from the request data and persists it to the database.
 *
 * Lifecycle:
 * 1. beforeCreateFill() - Transform or add data before validation
 * 2. createValidateRules() - Validate data using Laravel validation rules
 * 3. beforeCreate() - Final modifications before persistence
 * 4. Model::create() - Persist the record
 * 5. afterCreate() - Post-creation operations (events, cache, etc.)
 *
 * @method string modelClassName() Returns the Eloquent model class name
 * @method JsonResponse|ResponseInterface answerEmptyPayload() Returns empty payload error
 * @method JsonResponse|ResponseInterface answerSuccess(array $data, array $meta, HttpStatusCode $code) Returns success response
 * @method JsonResponse|ResponseInterface runValidation(array $data, array $rules) Validates data and returns error response if fails
 */
trait Create
{
    /**
     * Creates a new record from the request data.
     *
     * @param Request $request The HTTP request containing the model data
     * @param string|null $method Model serialization method (default from config or 'toArray')
     * @return JsonResponse|ResponseInterface JSON response with 201 Created on success, or error response
     */
    public function create(Request $request, ?string $method = null): JsonResponse|ResponseInterface
    {
        $method = $method ?? config('devToolbelt.fast-crud.create.method', 'toArray');
        $data = $request->post();

        if (empty($data)) {
            return $this->answerEmptyPayload();
        }

        $this->beforeCreateFill($data);

        $validationResponse = $this->runValidation($data, $this->createValidateRules());

        if ($validationResponse !== null) {
            return $validationResponse;
        }

        $this->beforeCreate($data);

        $modelName = $this->modelClassName();
        $record = $modelName::query()->create($data);

        $this->afterCreate($record);

        return $this->answerSuccess(
            data: $record->{$method}(),
            code: HttpStatusCode::CREATED
        );
    }

    /**
     * Hook called before filling the model with request data during creation.
     *
     * Override this method to transform, validate, or add additional data
     * before the model is filled.
     *
     * @param array<string, mixed> $data Request data to be filled into the model (passed by reference)
     */
    protected function beforeCreateFill(array &$data): void
    {
    }

    /**
     * Hook called before the model is created.
     *
     * Override this method for additional validations or
     * final modifications to the data before persistence.
     *
     * @param array<string, mixed> $data The data to be used for creation (passed by reference)
     */
    protected function beforeCreate(array &$data): void
    {
    }

    /**
     * Hook called after the model has been successfully created.
     *
     * Override this method for post-creation operations like dispatching events,
     * creating related models, or logging.
     *
     * @param Model $record The created model instance
     */
    protected function afterCreate(Model $record): void
    {
    }

    /**
     * Define validation rules for the create action.
     *
     * Override this method to return Laravel validation rules.
     * If rules are defined, the data will be validated after beforeCreateFill()
     * and before beforeCreate().
     *
     * @return array<string, mixed> Laravel validation rules
     *
     * @example
     * ```php
     * protected function createValidateRules(): array
     * {
     *     return [
     *         'name' => ['required', 'string', 'max:255'],
     *         'email' => ['required', 'email', 'unique:users'],
     *         'price' => ['required', 'numeric', 'min:0'],
     *     ];
     * }
     * ```
     */
    protected function createValidateRules(): array
    {
        return [];
    }
}
