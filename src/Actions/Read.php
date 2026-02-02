<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Database\Eloquent\Builder;

trait Read
{
    public function read(string $id): JsonResponse|ResponseInterface
    {
        if (!Str::isUuid($id)) {
            return $this->answerInvalidUuid();
        }

        $modelName = $this->modelClassName();
        $query = $modelName::query()->where('external_id', $id);
        $this->modifyReadQuery($query);

        /** @var Model $record */
        $record = $query->first();

        if (!$record) {
            return $this->answerRecordNotFound();
        }

        return $this->answerSuccess($record->toArray());
    }

    protected function modifyReadQuery(Builder $query): void
    {
    }
}
