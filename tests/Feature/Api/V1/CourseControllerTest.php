<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_course_details(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        Http::fake(fn () => Http::response([
            [
                'id' => 22,
                'shortname' => 'CX',
                'fullname' => 'Customer Experience',
                'categoryid' => 3,
                'visible' => 1,
                'startdate' => 1750000000,
                'enddate' => 0,
            ],
        ]));

        $this->getJson('/api/v1/courses/22')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => 22,
                    'short_name' => 'CX',
                    'full_name' => 'Customer Experience',
                    'end_date' => null,
                ],
            ]);
    }

    public function test_show_returns_404_for_unknown_course(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        Http::fake(fn () => Http::response([]));

        $this->getJson('/api/v1/courses/999')
            ->assertNotFound();
    }
}
