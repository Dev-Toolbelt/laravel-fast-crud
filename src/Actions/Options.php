<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Database\Eloquent\Builder;

trait Options
{
    public function options(Request $request): JsonResponse|ResponseInterface
    {
        $value = $request->get('value', 'external_id');
        $label = $request->get('label');

        if (is_null($label)) {
            return $this->answerRequired('label');
        }

        $modelName = $this->modelClassName();

        /** @var Model $model */
        $model = new $modelName();

        if (!$model->hasModelAttribute($label)) {
            return $this->answerColumnNotFound('label');
        }

        $query = $model->newQuery()
            ->select([$value . ' as value', $label . ' as label'])
            ->orderBy($label, 'ASC');

        $this->modifyOptionsQuery($query);

        $records = $query->get()->toArray();

        $rows = array_map(function (array $record) {
            return [
                'label' => $record['label'],
                'value' => $record['value'],
            ];
        }, $records);

        return $this->answerSuccess($rows);
    }

    protected function modifyOptionsQuery(Builder $query): void
    {
    }
}
