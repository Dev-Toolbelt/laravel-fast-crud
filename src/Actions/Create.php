<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use DevToolbelt\Enums\Http\HttpStatusCode;

trait Create
{
    public function create(Request $request): JsonResponse|ResponseInterface
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
            data: $record->toSoftArray(),
            code: HttpStatusCode::CREATED
        );
    }
}
