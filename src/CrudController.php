<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud;

use DevToolbelt\JsendPayload\AnswerTrait;
use DevToolbelt\LaravelFastCrud\Actions\Create;
use DevToolbelt\LaravelFastCrud\Actions\Delete;
use DevToolbelt\LaravelFastCrud\Actions\ExportCsv;
use DevToolbelt\LaravelFastCrud\Actions\Options;
use DevToolbelt\LaravelFastCrud\Actions\Read;
use DevToolbelt\LaravelFastCrud\Actions\Restore;
use DevToolbelt\LaravelFastCrud\Actions\Search;
use DevToolbelt\LaravelFastCrud\Actions\SoftDelete;
use DevToolbelt\LaravelFastCrud\Actions\Update;
use DevToolbelt\LaravelFastCrud\Traits\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Validation\ValidatesRequests;

/**
 * Abstract base controller providing full CRUD operations for Eloquent models.
 *
 * This controller composes all CRUD action traits (Create, Read, Update, Delete,
 * SoftDelete, Restore, Search, Options, ExportCsv) and provides hook methods
 * for customization without overriding the entire action logic.
 *
 * Included Traits:
 * - AnswerTrait: JSend response formatting
 * - ValidatesRequests: Laravel validation support
 * - Helpers: Utility methods (hasModelAttribute, runValidation)
 * - Create, Read, Update, Delete, SoftDelete, Restore, Search, Options, ExportCsv: CRUD actions
 *
 * @example
 * ```php
 * class ProductController extends CrudController
 * {
 *     protected function modelClassName(): string
 *     {
 *         return Product::query()->class;
 *     }
 *
 *     protected function createValidateRules(): array
 *     {
 *         return [
 *             'name' => ['required', 'string', 'max:255'],
 *             'price' => ['required', 'numeric', 'min:0'],
 *         ];
 *     }
 *
 *     protected function beforeCreateFill(array &$data): void
 *     {
 *         $data['created_by'] = auth()->id();
 *     }
 * }
 * ```
 */
abstract class CrudController
{
    use AnswerTrait;
    use ValidatesRequests;
    use Helpers;
    use Create;
    use Read;
    use Update;
    use Delete;
    use SoftDelete;
    use Restore;
    use Search;
    use Options;
    use ExportCsv;

    /**
     * Returns the fully qualified class name of the Eloquent model.
     *
     * @return class-string<Model> The model class name
     */
    abstract protected function modelClassName(): string;
}
