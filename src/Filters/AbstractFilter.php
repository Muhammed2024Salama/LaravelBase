<?php

declare(strict_types=1);

namespace MuhammedSalama\Base\Filters;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class AbstractFilter
{
    /**
     * Whitelisted filter columns mapped to their SQL operator.
     * Only keys present in this array are applied from the request.
     *
     * @var array<string, string>
     */
    protected array $filters = [];

    /**
     * Columns the consumer may sort by via ?sort_by=column&sort_dir=asc|desc.
     *
     * @var list<string>
     */
    protected array $sortable = [];

    /**
     * Columns searched with a single LIKE %term% when ?search=term is present.
     *
     * @var list<string>
     */
    protected array $searchable = [];

    private bool $applied = false;

    public function __construct(
        protected Request $request,
        protected Builder $query,
    ) {}

    /**
     * Apply all filters to the query. Idempotent: safe to call multiple times.
     */
    public function apply(): static
    {
        if ($this->applied) {
            return $this;
        }
        $this->applied = true;

        $this->applyFilters();
        $this->applySearch();
        $this->applySorting();

        return $this;
    }

    /**
     * Apply whitelisted column filters from the request.
     */
    protected function applyFilters(): void
    {
        foreach ($this->filters as $column => $operator) {
            if (! $this->request->has($column)) {
                continue;
            }

            $value = $this->request->get($column);

            if (is_array($value) || is_object($value)) {
                continue;
            }

            if (strtolower($operator) === 'like') {
                $this->query->where($column, 'LIKE', '%'.$value.'%');
            } else {
                $this->query->where($column, $operator, $value);
            }
        }
    }

    /**
     * Apply full-text LIKE search across all $searchable columns.
     */
    protected function applySearch(): void
    {
        if (! $this->request->filled('search') || $this->searchable === []) {
            return;
        }

        $rawTerm = $this->request->get('search', '');

        if (! is_scalar($rawTerm)) {
            return;
        }

        $term = (string) $rawTerm;
        $columns = $this->searchable;

        $this->query->where(function (Builder $q) use ($term, $columns): void {
            foreach ($columns as $column) {
                $q->orWhere($column, 'LIKE', '%'.$term.'%');
            }
        });
    }

    /**
     * Apply ORDER BY when ?sort_by= names a whitelisted column.
     * Sort direction defaults to 'asc'; only 'desc' is accepted as an override.
     */
    protected function applySorting(): void
    {
        $sortBy = $this->request->get('sort_by');

        if (! is_string($sortBy) || ! in_array($sortBy, $this->sortable, true)) {
            return;
        }

        $rawDirection = $this->request->get('sort_dir', 'asc');
        $direction = is_string($rawDirection) ? strtolower($rawDirection) : 'asc';

        $this->query->orderBy($sortBy, $direction === 'desc' ? 'desc' : 'asc');
    }

    /**
     * Apply filters then paginate.
     *
     * Respects ?per_page= from the request, clamped to [1, 100].
     * Pass $perPage to override the default (15).
     */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        $requested = $this->request->integer('per_page', $perPage);
        $perPage = max(1, min($requested, 100));

        return $this->apply()->query->paginate($perPage);
    }

    /**
     * Return the query builder after all filters are applied.
     * Useful when you need to add extra constraints before executing.
     */
    public function getQuery(): Builder
    {
        return $this->apply()->query;
    }
}
