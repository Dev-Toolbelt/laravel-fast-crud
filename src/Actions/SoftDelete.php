<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides the soft delete action for CRUD controllers.
 *
 * Soft deletes a model instance by updating configurable fields (deleted_at, deleted_by).
 * Does not require the model to use Laravel's SoftDeletes trait.
 *
 * @method string modelClassName() Returns the Eloquent model class name
 * @method bool hasModelAttribute(Model $model, string $attributeName) Checks if model has attribute
 * @method JsonResponse|ResponseInterface answerInvalidUuid() Returns invalid UUID error response
 * @method JsonResponse|ResponseInterface answerRecordNotFound() Returns not found error response
 * @method JsonResponse|ResponseInterface answerColumnNotFound(string $field) Returns column not found error response
 * @method JsonResponse|ResponseInterface answerNoContent() Returns 204 No Content response
 */
trait SoftDelete
{
    /**
     * Soft deletes a record by its identifier field.
     *
     * @param string $id The identifier value of the record to soft delete
     * @return JsonResponse|ResponseInterface 204 No Content on success, or error response
     */
    public function softDelete(string $id): JsonResponse|ResponseInterface
    {
        $findField = config('devToolbelt.fast_crud.soft_delete.find_field')
            ?? config('devToolbelt.fast_crud.global.find_field', 'id');

        $isUuid = config('devToolbelt.fast_crud.soft_delete.find_field_is_uuid')
            ?? config('devToolbelt.fast_crud.global.find_field_is_uuid', false);

        $deletedAtField = config('devToolbelt.fast_crud.soft_delete.deleted_at_field', 'deleted_at');
        $deletedByField = config('devToolbelt.fast_crud.soft_delete.deleted_by_field', 'deleted_by');

        if ($isUuid && !Str::isUuid($id)) {
            return $this->answerInvalidUuid();
        }

        $modelName = $this->modelClassName();

        /** @var Model $model */
        $model = new $modelName();

        if (!$this->hasModelAttribute($model, $deletedAtField)) {
            return $this->answerColumnNotFound($deletedAtField);
        }

        if (!$this->hasModelAttribute($model, $deletedByField)) {
            return $this->answerColumnNotFound($deletedByField);
        }

        $query = $modelName::query()->where($findField, $id);
        $this->modifySoftDeleteQuery($query);

        /** @var Model|null $record */
        $record = $query->first();

        if ($record === null) {
            return $this->answerRecordNotFound();
        }

        $this->beforeSoftDelete($record);

        $record->update([
            $deletedAtField => Carbon::now(),
            $deletedByField => $this->getSoftDeleteUserId(),
        ]);

        $this->afterSoftDelete($record);

        return $this->answerNoContent();
    }

    /**
     * Hook to modify the soft delete query before fetching the record.
     *
     * Override this method to add additional conditions or scopes,
     * such as ensuring the user owns the record.
     *
     * @param Builder $query The query builder instance
     *
     * @example
     * ```php
     * protected function modifySoftDeleteQuery(Builder $query): void
     * {
     *     $query->where('user_id', auth()->id());
     * }
     * ```
     */
    protected function modifySoftDeleteQuery(Builder $query): void
    {
    }

    /**
     * Hook called before the record is soft deleted.
     *
     * Override this method to perform actions before soft deletion,
     * such as logging, validation, or cleanup of related data.
     *
     * @param Model $record The model instance about to be soft deleted
     */
    protected function beforeSoftDelete(Model $record): void
    {
    }

    /**
     * Hook called after the record has been soft deleted.
     *
     * Override this method to perform post-soft-deletion actions,
     * such as clearing cache, dispatching events, or audit logging.
     *
     * @param Model $record The soft deleted model instance
     */
    protected function afterSoftDelete(Model $record): void
    {
    }

    /**
     * Returns the ID of the user performing the soft delete.
     *
     * Override this method to provide the authenticated user ID
     * or any other identifier for audit purposes.
     *
     * @return int|string|null The user ID or null if not available
     *
     * @example
     * ```php
     * protected function getSoftDeleteUserId(): ?int
     * {
     *     return auth()->id();
     * }
     * ```
     */
    protected function getSoftDeleteUserId(): int|string|null
    {
        return auth()->id();
    }
}
