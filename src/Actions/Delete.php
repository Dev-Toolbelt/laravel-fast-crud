<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use DevToolbelt\Enums\Http\HttpStatusCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides the delete (DELETE by ID) action for CRUD controllers.
 *
 * Deletes a model instance by its identifier field (configurable).
 * If the model uses Laravel's SoftDeletes trait, delegates to softDelete() action.
 * Otherwise, performs a hard delete using forceDelete().
 *
 * @method string modelClassName() Returns the Eloquent model class name
 * @method JsonResponse|ResponseInterface answerInvalidUuid(HttpStatusCode $code) Returns invalid UUID error response
 * @method JsonResponse|ResponseInterface answerRecordNotFound() Returns not found error response
 * @method JsonResponse|ResponseInterface answerNoContent(HttpStatusCode $code) Returns No Content response
 * @method JsonResponse|ResponseInterface softDelete(string $id) Soft deletes a record (from SoftDelete trait)
 */
trait Delete
{
    /**
     * Deletes a record by its identifier field.
     *
     * If the model uses Laravel's SoftDeletes trait, this method delegates
     * to the softDelete() action. Otherwise, it performs a hard delete.
     *
     * @param string $id The identifier value of the record to delete
     * @return JsonResponse|ResponseInterface 204 No Content on success, or error response
     */
    public function delete(string $id): JsonResponse|ResponseInterface
    {
        $modelName = $this->modelClassName();

        // Check if model uses SoftDeletes trait - delegate to softDelete action
        if ($this->modelUsesSoftDeletes($modelName)) {
            return $this->softDelete($id);
        }

        return $this->performHardDelete($id);
    }

    /**
     * Checks if the model uses Laravel's SoftDeletes trait.
     *
     * @param string $modelName The fully qualified model class name
     * @return bool True if the model uses SoftDeletes trait
     */
    protected function modelUsesSoftDeletes(string $modelName): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($modelName), true);
    }

    /**
     * Performs a hard delete on the record.
     *
     * @param string $id The identifier value of the record to delete
     * @return JsonResponse|ResponseInterface 204 No Content on success, or error response
     */
    protected function performHardDelete(string $id): JsonResponse|ResponseInterface
    {
        $httpStatus = HttpStatusCode::from(
            config('devToolbelt.fast-crud.delete.http_status', HttpStatusCode::OK->value)
        );

        $validationHttpStatus = HttpStatusCode::from(
            config('devToolbelt.fast-crud.global.validation.http_status', HttpStatusCode::BAD_REQUEST->value)
        );

        $findField = config('devToolbelt.fast-crud.delete.find_field')
            ?? config('devToolbelt.fast-crud.global.find_field', 'id');

        $isUuid = config('devToolbelt.fast-crud.delete.find_field_is_uuid')
            ?? config('devToolbelt.fast-crud.global.find_field_is_uuid', false);

        if ($isUuid && !Str::isUuid($id)) {
            return $this->answerInvalidUuid($validationHttpStatus);
        }

        $modelName = $this->modelClassName();
        $query = $modelName::query()->where($findField, $id);
        $this->modifyDeleteQuery($query);

        /** @var Model|null $record */
        $record = $query->first();

        if ($record === null) {
            return $this->answerRecordNotFound();
        }

        $this->beforeDelete($record);
        $record->delete();
        $this->afterDelete($record);

        return $this->answerNoContent(code: $httpStatus);
    }

    /**
     * Hook to modify the delete query before fetching the record.
     *
     * Override this method to add additional conditions or scopes,
     * such as ensuring the user owns the record.
     *
     * @param Builder $query The query builder instance
     *
     * @example
     * ```php
     * protected function modifyDeleteQuery(Builder $query): void
     * {
     *     $query->where('user_id', auth()->id());
     * }
     * ```
     */
    protected function modifyDeleteQuery(Builder $query): void
    {
    }

    /**
     * Hook called before the record is deleted.
     *
     * Override this method to perform actions before deletion,
     * such as logging, validation, or cleanup of related data.
     *
     * @param Model $record The model instance about to be deleted
     */
    protected function beforeDelete(Model $record): void
    {
    }

    /**
     * Hook called after the record has been deleted.
     *
     * Override this method to perform post-deletion actions,
     * such as clearing cache, dispatching events, or audit logging.
     *
     * @param Model $record The deleted model instance
     */
    protected function afterDelete(Model $record): void
    {
    }
}
