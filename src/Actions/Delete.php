<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Database\Eloquent\Builder;

trait Delete
{
    public function delete(string $id): JsonResponse|ResponseInterface
    {
        if (!Str::isUuid($id)) {
            return $this->answerInvalidUuid();
        }

        $modelName = $this->modelClassName();
        $query = $modelName::query()->where('external_id', $id);
        $this->modifyDeleteQuery($query);

        /** @var Model $record */
        $record = $query->first();

        if (!$record) {
            return $this->answerRecordNotFound();
        }

        $record->delete();

        return $this->answerNoContent();
    }

    protected function modifyDeleteQuery(Builder $query): void
    {
    }
}
