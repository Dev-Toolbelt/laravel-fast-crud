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
 * Provides the read (GET by ID) action for CRUD controllers.
 *
 * Retrieves a single model instance by its identifier field (configurable).
 *
 * @method string modelClassName() Returns the Eloquent model class name
 * @method JsonResponse|ResponseInterface answerInvalidUuid() Returns invalid UUID error response
 * @method JsonResponse|ResponseInterface answerRecordNotFound() Returns not found error response
 * @method JsonResponse|ResponseInterface answerSuccess(array $data, array $meta = []) Returns success response
 */
trait Read
{
    /**
     * Retrieves a single record by its identifier field.
     *
     * @param string $id The identifier value of the record
     * @param string|null $method Model serialization method (default from config or 'toArray')
     * @return JsonResponse|ResponseInterface JSON response with the record data or error response
     */
    public function read(string $id, ?string $method = null): JsonResponse|ResponseInterface
    {
        $method = $method ?? config('devToolbelt.fast-crud.read.method', 'toArray');
        $httpStatus = HttpStatusCode::from(
            config('devToolbelt.fast-crud.read.http_status', HttpStatusCode::OK->value)
        );

        $findField = config('devToolbelt.fast-crud.read.find_field')
            ?? config('devToolbelt.fast-crud.global.find_field', 'id');

        $isUuid = config('devToolbelt.fast-crud.read.find_field_is_uuid')
            ?? config('devToolbelt.fast-crud.global.find_field_is_uuid', false);

        if ($isUuid && !Str::isUuid($id)) {
            return $this->answerInvalidUuid();
        }

        $modelName = $this->modelClassName();
        $query = $modelName::query()->where($findField, $id);
        $this->modifyReadQuery($query);

        /** @var Model|null $record */
        $record = $query->first();

        if ($record === null) {
            return $this->answerRecordNotFound();
        }

        $response = $this->answerSuccess(data: $record->{$method}(), code: $httpStatus);
        $this->afterRead($record);

        return $response;
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

    /**
     * Hook called after the record has been read and response prepared.
     *
     * Override this method to perform post-read actions,
     * such as logging, analytics, or cache warming.
     *
     * @param Model $record The model instance that was read
     */
    protected function afterRead(Model $record): void
    {
    }
}
