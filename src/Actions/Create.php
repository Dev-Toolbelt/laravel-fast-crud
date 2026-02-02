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
 * Executes the lifecycle: beforeCreateFill() -> beforeCreate() -> create() -> afterCreate()
 *
 * @method string modelClassName() Returns the Eloquent model class name
 * @method JsonResponse|ResponseInterface answerEmptyPayload() Returns empty payload error
 * @method JsonResponse|ResponseInterface answerSuccess(array $data, array $meta, HttpStatusCode $code)
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
        $method = $method ?? config('devToolbelt.fast_crud.create.method', 'toArray');
        $data = $request->post();

        if (empty($data)) {
            return $this->answerEmptyPayload();
        }

        $this->beforeCreateFill($data);
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
}
