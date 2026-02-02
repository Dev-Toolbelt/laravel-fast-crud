<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud;

use DevToolbelt\JsendPayload\AnswerTrait;
use DevToolbelt\LaravelFastCrud\Actions\Create;
use DevToolbelt\LaravelFastCrud\Actions\Delete;
use DevToolbelt\LaravelFastCrud\Actions\ExportCsv;
use DevToolbelt\LaravelFastCrud\Actions\Options;
use DevToolbelt\LaravelFastCrud\Actions\Read;
use DevToolbelt\LaravelFastCrud\Actions\Search;
use DevToolbelt\LaravelFastCrud\Actions\Update;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Abstract base controller providing full CRUD operations for Eloquent models.
 *
 * This controller composes all CRUD action traits (Create, Read, Update, Delete, Search,
 * Options, ExportCsv) and provides hook methods for customization without overriding
 * the entire action logic.
 *
 * @example
 * ```php
 * class ProductController extends CrudController
 * {
 *     protected function modelClassName(): string
 *     {
 *         return Product::class;
 *     }
 *
 *     protected function beforeSave(Model $record): void
 *     {
 *         $record->slug = Str::slug($record->name);
 *     }
 * }
 * ```
 */
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

    /**
     * Returns the fully qualified class name of the Eloquent model.
     *
     * @return class-string<Model> The model class name
     */
    abstract protected function modelClassName(): string;

    /**
     * Hook called before filling the model with request data.
     *
     * Use this to transform, validate, or add additional data before
     * the model is filled. The data array is passed by reference.
     *
     * @param array<string, mixed> $data Request data to be filled into the model
     */
    protected function beforeFill(array &$data): void
    {
    }

    /**
     * Hook called after filling the model but before saving to database.
     *
     * Use this for additional validations, setting computed fields,
     * or any logic that needs the filled model before persistence.
     *
     * @param Model $record The model instance about to be saved
     */
    protected function beforeSave(Model $record): void
    {
    }

    /**
     * Hook called after the model has been successfully saved to database.
     *
     * Use this for post-save operations like dispatching events,
     * updating related models, or logging.
     *
     * @param Model $record The saved model instance
     */
    protected function afterSave(Model $record): void
    {
    }
}
