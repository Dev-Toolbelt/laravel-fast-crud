<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Psr\Http\Message\ResponseInterface;

/**
 * Provides helper methods for CRUD controllers.
 *
 * This trait contains utility methods used across multiple CRUD actions:
 * - hasModelAttribute(): Check if a model has a specific attribute
 * - runValidation(): Validate data using Laravel validation rules
 *
 * @method JsonResponse|ResponseInterface answerFail(array $data) Returns JSend fail response
 */
trait Helpers
{
    /**
     * Checks if a model has a given attribute.
     *
     * Combines fillable, guarded, original, casts, and appends properties
     * to determine if the attribute exists on the model.
     *
     * @param Model $model The model instance to check
     * @param string $attributeName The attribute name to look for
     * @return bool True if the attribute exists, false otherwise
     *
     * @example
     * ```php
     * $model = new Product();
     * $this->hasModelAttribute($model, 'name'); // true if 'name' is fillable/guarded/etc.
     * ```
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

    /**
     * Run validation on the given data using the provided rules.
     *
     * If validation fails, returns a JSend fail response with the following error format:
     * ```json
     * {
     *     "status": "fail",
     *     "data": [
     *         {
     *             "field": "email",
     *             "error": "required",
     *             "value": null,
     *             "message": "The email field is required."
     *         }
     *     ],
     *     "meta": []
     * }
     * ```
     *
     * @param array<string, mixed> $data The data to validate
     * @param array<string, mixed> $rules Laravel validation rules
     * @return JsonResponse|ResponseInterface|null Returns error response if validation fails, null if passes
     */
    protected function runValidation(array $data, array $rules): JsonResponse|ResponseInterface|null
    {
        if (empty($rules)) {
            return null;
        }

        $validator = Validator::make($data, $rules);

        if ($validator->passes()) {
            return null;
        }

        $errors = [];
        $messages = $validator->errors()->toArray();

        foreach ($validator->failed() as $field => $failedRules) {
            $fieldMessages = $messages[$field] ?? [];
            $i = 0;

            foreach ($failedRules as $ruleName => $params) {
                $errors[] = [
                    'field' => $field,
                    'error' => strtolower($ruleName),
                    'value' => $data[$field] ?? null,
                    'message' => $fieldMessages[$i] ?? null,
                ];
                $i++;
            }
        }

        return $this->answerFail($errors);
    }
}
