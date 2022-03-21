<?php

namespace Humweb\Table\Tests\Unit;


use Humweb\Table\Tests\Models\User;
use Humweb\Table\Tests\Models\UserResource;
use Humweb\Table\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class ResourceTest extends TestCase
{
    protected $resource;

    protected function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub
        User::factory(10)->create();
        $this->resource = UserResource::make($this->request());
    }

    /**
     * @return void
     */
    public function test_it_can_run_default_query_on_db()
    {
        DB::enableQueryLog();
        $output = $this->resource->paginate();
        $this->assertQueryLogContains('select count(*) as aggregate from "test_users"');
        $this->assertQueryLogContains('select * from "test_users" order by "id" asc limit 15 offset 0');
    }


    /**
     * @return void
     */
    public function test_it_can_apply_search()
    {
        DB::enableQueryLog();
        $this->resource = UserResource::make($this->request(function (Request $request) {
            $request->query->set('search', [
                'name' => 'foobar'
            ]);
        }));
        $output         = $this->resource->paginate();
//        $this->assertQueryLogContains('select count(*) as aggregate from "test_users"');
        $this->assertQueryLogContains('select count(*) as aggregate from "test_users" where LOWER(\'name\') like ?');
    }

    /**
     * @return void
     */
    public function test_it_can_apply_serach_with_pgsql_driver()
    {
        DB::enableQueryLog();
        $this->resource = UserResource::make($this->request(function (Request $request) {
            $request->query->set('search', [
                'name' => 'foobar'
            ]);
        }));

        $this->resource->driver = 'pgsql';
        $this->resource->whereLike('name', 'foobar');
        $output = $this->resource->getQuery()->toSql();

        $this->assertEquals('select * from "test_users" where name ilike ?', $output);
    }


    /**
     * @return void
     */
    public function test_it_apply_custom_parameter_filters()
    {
        DB::enableQueryLog();
        $run = false;
        $this->resource->addParameter('site', function () use (&$run) {
            $run = true;
        });
        $output = $this->resource->paginate();
        $this->assertTrue($run);
    }

    /**
     * @return void
     */
    public function test_it_add_parameters()
    {
        DB::enableQueryLog();
        $run = false;
        $this->resource->addParameter('site', 'machado');
        $this->assertEquals('machado', $this->resource->parameters['site']);

        $this->resource->addParameter(['id' => 33]);
        $this->assertEquals(33, $this->resource->parameters['id']);
    }

    /**
     * @return void
     */
    public function test_it_can_use_default_sort_method()
    {
        DB::enableQueryLog();

        $this->resource = new class($this->request()) extends UserResource {
            public function defaultSort(Builder $query)
            {
                $query->orderBy('name', 'desc');
            }
        };

        $this->resource->paginate();

        $this->assertQueryLogContains('select * from "test_users" order by "name" desc limit 15 offset 0');
    }

    /**
     * @return void
     */
    public function test_it_can_apply_sort_from_request()
    {
        DB::enableQueryLog();

        $this->resource = UserResource::make($this->request(function (Request $request) {
            $request->query->set('sort', '-name');
        }));

        $this->resource->paginate();

        $this->assertQueryLogContains('select * from "test_users" order by "name" desc limit 15 offset 0');

        $this->resource = UserResource::make($this->request(function (Request $request) {
            $request->query->set('sort', 'name');
        }));

        $this->resource->paginate();
        $this->assertQueryLogContains('select * from "test_users" order by "name" asc limit 15 offset 0');
    }

    /**
     * @return void
     */
    public function test_it_can_apply_global_filter()
    {
        DB::enableQueryLog();

        $this->resource = UserResource::make($this->request(function (Request $request) {
            $request->query->set('search', [
                'global' => 1
            ]);
        }));

        $this->resource->paginate();

        $this->assertQueryLogContains('select * from "test_users" where ("id" = ?) order by "id" asc limit 15 offset 0');
    }
}
