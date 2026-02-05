<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use DevToolbelt\Enums\Http\HttpStatusCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides the restore action for CRUD controllers.
 *
 * Restores a soft deleted model instance by clearing the deleted_at and deleted_by fields.
 * Uses the same field configuration as the SoftDelete action.
 *
 * @method string modelClassName() Returns the Eloquent model class name
 * @method bool hasModelAttribute(Model $model, string $attributeName) Checks if model has attribute
 * @method JsonResponse|ResponseInterface answerInvalidUuid() Returns invalid UUID error response
 * @method JsonResponse|ResponseInterface answerRecordNotFound() Returns not found error response
 * @method JsonResponse|ResponseInterface answerColumnNotFound(string $field) Returns column not found error response
 * @method JsonResponse|ResponseInterface answerSuccess(array $data, array $meta = []) Returns success response
 */
trait Restore
{
    /**
     * Restores a soft deleted record by its identifier field.
     *
     * @param string $id The identifier value of the record to restore
     * @param string|null $method Model serialization method (default from config or 'toArray')
     * @return JsonResponse|ResponseInterface JSON response with the restored record or error response
     */
    public function restore(string $id, ?string $method = null): JsonResponse|ResponseInterface
    {
        $method = $method ?? config('devToolbelt.fast-crud.restore.method', 'toArray');
        $httpStatus = HttpStatusCode::from(
            config('devToolbelt.fast-crud.restore.http_status', HttpStatusCode::OK->value)
        );

        $findField = config('devToolbelt.fast-crud.restore.find_field')
            ?? config('devToolbelt.fast-crud.global.find_field', 'id');

        $isUuid = config('devToolbelt.fast-crud.restore.find_field_is_uuid')
            ?? config('devToolbelt.fast-crud.global.find_field_is_uuid', false);

        $deletedAtField = config('devToolbelt.fast-crud.soft_delete.deleted_at_field', 'deleted_at');
        $deletedByField = config('devToolbelt.fast-crud.soft_delete.deleted_by_field', 'deleted_by');

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

        $query = $modelName::query()
            ->where($findField, $id)
            ->whereNotNull($deletedAtField)
            ->whereNotNull($deletedByField);

        $this->modifyRestoreQuery($query);

        /** @var Model|null $record */
        $record = $query->first();

        if ($record === null) {
            return $this->answerRecordNotFound();
        }

        $this->beforeRestore($record);

        $record->update([
            $deletedAtField => null,
            $deletedByField => null,
        ]);

        $this->afterRestore($record);

        return $this->answerSuccess(data: $record->{$method}(), code: $httpStatus);
    }

    /**
     * Hook to modify the restore query before fetching the record.
     *
     * Override this method to add additional conditions or scopes,
     * such as ensuring the user owns the record.
     *
     * @param Builder $query The query builder instance
     *
     * @example
     * ```php
     * protected function modifyRestoreQuery(Builder $query): void
     * {
     *     $query->where('user_id', auth()->id());
     * }
     * ```
     */
    protected function modifyRestoreQuery(Builder $query): void
    {
    }

    /**
     * Hook called before the record is restored.
     *
     * Override this method to perform actions before restoration,
     * such as logging or validation.
     *
     * @param Model $record The model instance about to be restored
     */
    protected function beforeRestore(Model $record): void
    {
    }

    /**
     * Hook called after the record has been restored.
     *
     * Override this method to perform post-restoration actions,
     * such as clearing cache, dispatching events, or audit logging.
     *
     * @param Model $record The restored model instance
     */
    protected function afterRestore(Model $record): void
    {
    }
}
