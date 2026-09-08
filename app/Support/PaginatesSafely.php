<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

final class PaginatesSafely
{
    /**
     * Paginate en revenant à la page 1 si la page demandée est hors limites
     * (ex. ?page=2 alors qu'il ne reste qu'un résultat).
     *
     * @param  Builder|QueryBuilder  $query
     */
    public static function paginate(
        Builder|QueryBuilder $query,
        Request $request,
        int $perPage = 15,
        string $pageName = 'page',
    ): LengthAwarePaginator {
        $page = max(1, (int) $request->input($pageName, 1));

        $run = fn (int $targetPage) => (clone $query)
            ->paginate($perPage, ['*'], $pageName, $targetPage)
            ->withQueryString();

        $paginator = $run($page);

        if ($paginator->isEmpty() && $paginator->total() > 0 && $paginator->currentPage() > 1) {
            return $run(1);
        }

        return $paginator;
    }
}
