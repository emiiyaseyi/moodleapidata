<?php

namespace Tests\Unit\Support;

use App\Support\FieldFilter;
use Illuminate\Http\Request;
use Tests\TestCase;

class FieldFilterTest extends TestCase
{
    public function test_returns_data_unchanged_without_fields_param(): void
    {
        $request = Request::create('/staff/x');
        $data = ['a' => 1, 'b' => 2];

        $this->assertSame($data, FieldFilter::apply($request, $data));
    }

    public function test_whitelists_top_level_keys_for_associative_data(): void
    {
        $request = Request::create('/staff/x', 'GET', ['fields' => 'a, c']);
        $data = ['a' => 1, 'b' => 2, 'c' => 3];

        $this->assertSame(['a' => 1, 'c' => 3], FieldFilter::apply($request, $data));
    }

    public function test_whitelists_keys_within_each_item_of_a_list(): void
    {
        $request = Request::create('/staff/x', 'GET', ['fields' => 'id']);
        $data = [
            ['id' => 1, 'name' => 'A'],
            ['id' => 2, 'name' => 'B'],
        ];

        $this->assertSame([
            ['id' => 1],
            ['id' => 2],
        ], FieldFilter::apply($request, $data));
    }
}
