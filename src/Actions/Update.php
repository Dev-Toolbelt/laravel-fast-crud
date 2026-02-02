<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;

trait Update
{
    public function update(Request $request, string $id): JsonResponse|ResponseInterface
    {
        if (!Str::isUuid($id)) {
            return $this->answerInvalidUuid();
        }

        $data = $request->post();

        if (empty($data)) {
            return $this->answerEmptyPayload();
        }

        $modelName = $this->modelClassName();
        $query = $modelName::query()->where('external_id', $id);

        /** @var Model $record */
        $record = $query->first();

        if (!$record) {
            return $this->answerRecordNotFound();
        }

        $this->beforeFill($data);
        $record->fill($data);
        $this->beforeSave($record);
        $record->save();
        $this->afterSave($record);

        return $this->answerSuccess($record->toArray());
    }
}
