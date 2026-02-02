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
 *     protected function beforeCreateSave(Model $record): void
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
     * Checks if a model has a given attribute.
     *
     * Combines fillable, guarded, original, casts, and appends properties
     * to determine if the attribute exists on the model.
     *
     * @param Model $model The model instance to check
     * @param string $attributeName The attribute name to look for
     * @return bool True if the attribute exists, false otherwise
     */
    protected function hasModelAttribute(Model $model, string $attributeName): bool
    {
        $attributes = array_unique([
            ...$model->getFillable(),
            ...$model->getGuarded(),
            ...array_keys($model->getOriginal()),
            ...array_keys($model->getCasts()),
            ...$model->getAppends(),
        ]);

        return in_array($attributeName, $attributes, true);
    }
}
