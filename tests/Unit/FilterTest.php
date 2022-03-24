<?php

namespace Humweb\Table\Tests\Unit;

use Humweb\Table\Filters\FilterCollection;
use Humweb\Table\Filters\SelectFilter;
use Humweb\Table\Filters\TextFilter;
use Humweb\Table\Tests\Models\User;
use Humweb\Table\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FilterTest extends TestCase
{
    protected $fieldCollection;

    protected function setUp(): void
    {
        parent::setUp(); // TODO: Change the autogenerated stub

        $this->fieldCollection = new FilterCollection([
            TextFilter::make('id', 'ID')->exact()->rules('numeric'),
            TextFilter::make('title', 'Title')->startsWith()->rules('string|max:255'),
            TextFilter::make('body', 'Body')->fullSearch()->rules('string|max:500'),
        ]);
    }

    /**
     * A basic test example.
     * @return void
     */
    public function test_it_can_build_validation_rules()
    {
        $rules = $this->fieldCollection->getValidationRules('field');

        $this->assertEquals('numeric', $rules['id'][0]);
        $this->assertEquals('string|max:255', $rules['title'][0]);
        $this->assertEquals('string|max:500', $rules['body'][0]);
    }

    public function test_it_can_set_query_match_type()
    {
        $fields = $this->fieldCollection->keyBy('field');

        $this->assertTrue($fields['id']->exact);
        $this->assertFalse($fields['id']->startsWith);
        $this->assertTrue($fields['title']->startsWith);
        $this->assertTrue($fields['body']->fullSearch);
    }

    public function test_it_generate_label_from_field()
    {
        $filter = TextFilter::make('first_name');

        $this->assertEquals('First Name', $filter->label);
    }

    public function test_it_can_test_apply_filter()
    {
        $builder = User::query();

        $filter = SelectFilter::make('colors', 'Colors', [
            'blue' => 'blue',
            'red' => 'red',
            'green' => 'green',
        ]);

        $filter->apply($this->request(), $builder, 'blue');

        $this->assertEquals('select * from "test_users" where "colors" = ?', $builder->toSql());
        $this->assertEquals('blue', $builder->getBindings()[0]);
    }

    public function test_it_can_test_where_multiple_apply_filter()
    {
        $builder = User::query();

        $filter = SelectFilter::make('colors', 'Colors', [
            'blue' => 'blue',
            'red' => 'red',
            'green' => 'green',
        ])->multiple();

        $filter->apply($this->request(), $builder, ['blue', 'red']);

        $this->assertEquals('select * from "test_users" where "colors" in (?, ?)', $builder->toSql());
        $this->assertTrue($filter->jsonSerialize()['multiple']);
    }

    public function test_it_can_test_where_multiple_filter()
    {
        $builder = User::query();

        $filter = SelectFilter::make('colors', 'Colors', [
            'blue' => 'blue',
            'red' => 'red',
            'green' => 'green',
        ])->multiple();

        $filter->whereFilter($builder, ['blue', 'red']);

        $this->assertEquals('select * from "test_users" where "colors" in (?, ?)', $builder->toSql());
        $this->assertTrue($filter->jsonSerialize()['multiple']);
    }

    public function test_it_can_test_where_exact_filter()
    {
        $builder = User::query();

        $filter = TextFilter::make('colors')->exact();

        $filter->apply($this->request(), $builder, 'blue');

        $this->assertEquals('select * from "test_users" where "colors" = ?', $builder->toSql());
    }

    public function test_it_can_test_where_like_starts_with_filter()
    {
        $builder = $builder = User::query();

        $filter = TextFilter::make('colors')->startsWith();

        $filter->apply($this->request(), $builder, 'blue');


        $this->assertEquals('select * from "test_users" where colors like ?', $builder->toSql());
        $this->assertEquals('blue%', $builder->getBindings()[0]);
    }

    public function test_it_can_test_where_like_ends_with_filter()
    {
        $builder = User::query();

        $filter = TextFilter::make('colors')->endsWith();

        $filter->whereFilter($builder, 'blue');

        $this->assertEquals('%blue', $builder->getBindings()[0]);
    }

    public function test_it_can_test_where_like_full_search_filter()
    {
        $builder = User::query();

        $filter = TextFilter::make('colors')->fullSearch();

        $filter->whereFilter($builder, 'blue');

        $this->assertEquals('%blue%', $builder->getBindings()[0]);
    }

    public function test_it_can_filter_validation_rules()
    {
        $filter = TextFilter::make('colors')->rules('string');

        $this->assertEquals('string', $filter->getRules($this->request())[0]);
    }

    public function test_it_can_validate_collection()
    {
        $filters = new FilterCollection([
            TextFilter::make('colors')->rules('string'),
            TextFilter::make('name')->rules('string', 'max:5'),
        ]);

        $this->expectException(ValidationException::class);
        $filters->validateFilterInput($this->request(function (Request $request) {
            $request->query->set('colors', 'blue');
            $request->query->set('name', 'joebob');
        })->all());
    }
}
