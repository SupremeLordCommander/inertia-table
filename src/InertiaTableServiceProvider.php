<?php

namespace Humweb\Table;

use Illuminate\Support\ServiceProvider;
use Inertia\Response;

class InertiaTableServiceProvider extends ServiceProvider
{

    public function boot()
    {
        $request = $this->app['request'];
        Response::macro('table', function (callable $withTableBuilder = null)  use ($request) {
            $tableBuilder = new InertiaTable($request);

            if ($withTableBuilder) {
                $withTableBuilder($tableBuilder);
            }

            return $tableBuilder->shareProps($this);
        });
    }
}
