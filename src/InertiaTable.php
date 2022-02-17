<?php

namespace Humweb\InertiaTable;

use Humweb\InertiaTable\Filters\Filter;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Inertia\Response;

class InertiaTable
{
    private Request $request;
    private Collection $columns;
    private Collection $search;
    private Collection $filters;
    private bool $globalSearch = true;
    private bool $download = false;

    public function __construct(Request $request)
    {
        $this->request = $request;

        $this->columns = collect();
        $this->search  = collect();
        $this->filters = collect();
    }

    /**
     * Disable the global search.
     *
     * @return self
     */
    public function disableGlobalSearch(): InertiaTable
    {
        $this->globalSearch = false;

        return $this;
    }

    /**
     * Collects all properties and sets the default
     * values from the request query.
     *
     * @return array
     */
    public function getQueryBuilderProps(): array
    {
        $columns = $this->transformColumns();
        $search  = $this->transformSearch();
        $filters = $this->transformFilters();

        return [
            'sort'         => $this->request->query('sort'),
            'page'         => Paginator::resolveCurrentPage(),
            'columns'      => $columns->isNotEmpty() ? $columns->all() : (object) [],
            'search'       => $search->isNotEmpty() ? $search->all() : (object) [],
            'filters'      => $filters->isNotEmpty() ? $filters->all() : (object) [],
            'download'     => 0,
            'downloadable' => $this->download
        ];
    }

    /**
     * Transform the column collection for frontend.
     *
     * @return \Illuminate\Support\Collection
     */
    private function transformColumns(): Collection
    {
        $columns = $this->request->query('columns', []);

        if (empty($columns)) {
            return $this->columns;
        }

        return $this->columns->map(function ($column, $key) use ($columns) {
            if (!in_array($key, $columns)) {
                $column['enabled'] = false;
            }

            return $column;
        });
    }

    /**
     * Transform the search collection for the frontend
     *
     * @return \Illuminate\Support\Collection
     */
    private function transformSearch(): Collection
    {
        $search = $this->search->collect();

        if ($this->globalSearch) {
            $search->prepend([
                'key'   => 'global',
                'label' => 'global',
                'value' => null,
            ], 'global');
        }

        $filters = $this->request->query('filter', []);

        if (empty($filters)) {
            return $search;
        }

        return $search->map(function ($search, $key) use ($filters) {
            if (!array_key_exists($key, $filters)) {
                return $search;
            }

            $search['value']   = $filters[$key];
            $search['enabled'] = true;

            return $search;
        });
    }

    /**
     * Transform the filters collection for frontend
     *
     * @return \Illuminate\Support\Collection
     */
    private function transformFilters(): Collection
    {
        $filters = $this->request->query('filter', []);

        if (empty($filters)) {
            return $this->filters;
        }

        return $this->filters->map(function ($filter) use ($filters) {
            if (!array_key_exists($filter->key, $filters)) {
                return $filter->toArray();
            }

            $filter->value = $filters[$filter->key];

            if (!array_key_exists($filter->value, $filter->options ?? [])) {
                return $filter->toArray();
            }

//            $filter->value = $value;

            return $filter->toArray();
        });
    }

    /**
     * Share query builder props with Inertia response.
     *
     * @param  \Inertia\Response  $response
     *
     * @return \Inertia\Response
     */
    public function shareProps(Response $response): Response
    {
        return $response->with('queryBuilderProps', $this->getQueryBuilderProps());
    }

    /**
     * Add a column to the query builder.
     *
     * @param  string|array  $key
     * @param  string        $label
     * @param  bool          $enabled
     *
     * @return self
     */
    public function column(array|string $key, string $label, bool $enabled = true): self
    {
        $this->columns->put($key, [
            'key'     => $key,
            'label'   => $label,
            'enabled' => $enabled,
        ]);

        return $this;
    }

    public function columns(array $columns = []): InertiaTable
    {
        foreach ($columns as $key => $value) {
            if (is_array($value)) {
                $this->column($key, $value['value'], $value['enabled'] ?? true);
            } else {
                $this->column($key, $value, true);
            }
        }

        return $this;
    }

    public function columnAndSearchable(string $key, string $label, bool $enabled = true): InertiaTable
    {
        return $this->column($key, $label, $enabled)
            ->searchable($key, $label);
    }

    /**
     * Add a search row to the query builder.
     *
     * @param  string|array  $columns
     * @param  string|null   $label
     *
     * @return self
     */
    public function searchable(string|array $columns, string $label = null): InertiaTable
    {
        if (is_array($columns)) {
            foreach ($columns as $id => $label) {
                $this->searchable($id, $label);
            }
        } else {
            $this->search->put($columns, [
                'key'   => $columns,
                'label' => $label,
                'value' => null,
            ]);
        }

        return $this;
    }

    /**
     * Add a filter to the query builder.
     *
     * @param  Filter  $filter
     *
     * @return InertiaTable
     */
    public function filter(Filter $filter): InertiaTable
    {
        $this->filters->put($filter->key, $filter);

        return $this;
    }

    /**
     *
     * @return InertiaTable
     */
    public function downloadable(): InertiaTable
    {
        $this->download = true;

        return $this;
    }
}
