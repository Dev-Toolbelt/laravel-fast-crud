<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides the delete (DELETE by ID) action for CRUD controllers.
 *
 * Deletes a model instance by its external_id (UUID).
 * Supports soft deletes if the model uses the SoftDeletes trait.
 *
 * @method string modelClassName() Returns the Eloquent model class name
 * @method JsonResponse|ResponseInterface answerInvalidUuid() Returns invalid UUID error response
 * @method JsonResponse|ResponseInterface answerRecordNotFound() Returns not found error response
 * @method JsonResponse|ResponseInterface answerNoContent() Returns 204 No Content response
 */
trait Delete
{
    /**
     * Deletes a record by its external_id.
     *
     * @param string $id The external_id (UUID) of the record to delete
     * @return JsonResponse|ResponseInterface 204 No Content on success, or error response
     */
    public function delete(string $id): JsonResponse|ResponseInterface
    {
        if (!Str::isUuid($id)) {
            return $this->answerInvalidUuid();
        }

        $modelName = $this->modelClassName();
        $query = $modelName::query()->where('external_id', $id);
        $this->modifyDeleteQuery($query);

        /** @var Model|null $record */
        $record = $query->first();

        if ($record === null) {
            return $this->answerRecordNotFound();
        }

        $record->delete();

        return $this->answerNoContent();
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
}
