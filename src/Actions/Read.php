<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides the read (GET by ID) action for CRUD controllers.
 *
 * Retrieves a single model instance by its external_id (UUID).
 *
 * @method string modelClassName() Returns the Eloquent model class name
 * @method JsonResponse|ResponseInterface answerInvalidUuid() Returns invalid UUID error response
 * @method JsonResponse|ResponseInterface answerRecordNotFound() Returns not found error response
 * @method JsonResponse|ResponseInterface answerSuccess(array $data, array $meta = []) Returns success response
 */
trait Read
{
    /**
     * Retrieves a single record by its external_id.
     *
     * @param string $id The external_id (UUID) of the record
     * @return JsonResponse|ResponseInterface JSON response with the record data or error response
     */
    public function read(string $id, string $method = 'toArray'): JsonResponse|ResponseInterface
    {
        if (!Str::isUuid($id)) {
            return $this->answerInvalidUuid();
        }

        $modelName = $this->modelClassName();
        $query = $modelName::query()->where('external_id', $id);
        $this->modifyReadQuery($query);

        /** @var Model|null $record */
        $record = $query->first();

        if ($record === null) {
            return $this->answerRecordNotFound();
        }

        return $this->answerSuccess($record->{$method});
    }

    /**
     * Hook to modify the read query before execution.
     *
     * Override this method to add eager loading, additional conditions,
     * or scopes to the query.
     *
     * @param Builder $query The query builder instance
     *
     * @example
     * ```php
     * protected function modifyReadQuery(Builder $query): void
     * {
     *     $query->with(['category', 'tags']);
     * }
     * ```
     */
    protected function modifyReadQuery(Builder $query): void
    {
    }
}
