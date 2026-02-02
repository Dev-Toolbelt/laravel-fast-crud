<?php

declare(strict_types=1);

namespace DevToolbelt\LaravelFastCrud\Actions;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use DevToolbelt\LaravelFastCrud\Traits\Pageable;
use DevToolbelt\LaravelFastCrud\Traits\Sortable;
use DevToolbelt\LaravelFastCrud\Traits\Limitable;
use DevToolbelt\LaravelFastCrud\Traits\Searchable;
use Illuminate\Database\Eloquent\Builder;

trait Search
{
    use Searchable;
    use Sortable;
    use Limitable;
    use Pageable;

    /**
     * @throws Exception
     */
    public function search(Request $request, string $method = 'toSoftArray'): JsonResponse|ResponseInterface
    {
        $modelName = $this->modelClassName();

        /** @var Builder $query */
        $query = $modelName::query();

        $this->modifySearchQuery($query);
        $this->processSearch($query, $request->get('filter', []));
        $this->processSort($query, $request->input('sort', ''));
        $this->buildPagination($query, (int)$request->input('perPage', 40), $method);

        return $this->answerSuccess($this->data, meta: [
            'pagination' => $this->paginationData
        ]);
    }

    protected function modifySearchQuery(Builder $query): void
    {
    }
}
