<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud;

use DevToolbelt\JsendPayload\AnswerTrait;
use DevToolbelt\LaravelFastCrud\Actions\Read;
use DevToolbelt\LaravelFastCrud\Actions\Create;
use DevToolbelt\LaravelFastCrud\Actions\Delete;
use DevToolbelt\LaravelFastCrud\Actions\Search;
use DevToolbelt\LaravelFastCrud\Actions\Update;
use DevToolbelt\LaravelFastCrud\Actions\Options;
use DevToolbelt\LaravelFastCrud\Actions\ExportCsv;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class CrudController
{
    use AnswerTrait;
    use ValidatesRequests;
    use Create;
    use Read;
    use Update;
    use Delete;
    use Search;
    use Options;
    use ExportCsv;

    abstract protected function modelClassName(): string;

    protected function beforeFill(array &$data): void
    {
    }

    protected function beforeSave(Model $record): void
    {
    }

    protected function afterSave(Model $record): void
    {
    }
}
