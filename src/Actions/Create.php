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
 * Executes the lifecycle: beforeFill() -> fill() -> beforeSave() -> save() -> afterSave()
 *
 * @method string modelClassName() Returns the Eloquent model class name
 * @method void beforeFill(array &$data) Hook called before filling the model
 * @method void beforeSave(Model $record) Hook before saving
 * @method void afterSave(Model $record) Hook after saving
 * @method JsonResponse|ResponseInterface answerEmptyPayload() Returns empty payload error
 * @method JsonResponse|ResponseInterface answerSuccess(array $data, array $meta, HttpStatusCode $code)
 */
trait Create
{
    /**
     * Creates a new record from the request data.
     *
     * @param Request $request The HTTP request containing the model data
     * @return JsonResponse|ResponseInterface JSON response with 201 Created on success, or error response
     */
    public function create(Request $request, string $method = 'toArray'): JsonResponse|ResponseInterface
    {
        $data = $request->post();

        if (empty($data)) {
            return $this->answerEmptyPayload();
        }

        $modelName = $this->modelClassName();
        $record = new $modelName();

        $this->beforeFill($data);
        $record->fill($data);
        $this->beforeSave($record);
        $record->save();
        $this->afterSave($record);

        return $this->answerSuccess(
            data: $record->{$method}(),
            code: HttpStatusCode::CREATED
        );
    }
}
