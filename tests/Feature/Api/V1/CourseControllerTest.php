<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMoodle(): void
    {
        Http::fake(function (HttpRequest $request) {
            return match ($request['wsfunction'] ?? null) {
                'core_course_get_courses' => Http::response([
                    [
                        'id' => 22,
                        'shortname' => 'CX',
                        'fullname' => 'Customer Experience',
                        'categoryid' => 3,
                        'visible' => 1,
                        'startdate' => 1750000000,
                        'enddate' => 0,
                    ],
                ]),
                'core_enrol_get_enrolled_users' => Http::response([
                    [
                        'id' => 35,
                        'fullname' => 'Michael Adeniran',
                        'email' => 'michael@email.com',
                        'department' => 'Learning & Development',
                        'lastaccess' => 1751400000,
                        'roles' => [['roleid' => 5, 'shortname' => 'student']],
                    ],
                    [
                        'id' => 42,
                        'fullname' => 'Ada Obi',
                        'email' => 'ada@email.com',
                        'department' => 'Operations',
                        'lastaccess' => 0,
                        'roles' => [['roleid' => 5, 'shortname' => 'student']],
                    ],
                    [
                        'id' => 51,
                        'fullname' => 'Tunde Bello',
                        'email' => 'tunde@email.com',
                        'department' => 'Operations',
                        'lastaccess' => 0,
                        'roles' => [['roleid' => 5, 'shortname' => 'student']],
                    ],
                ]),
                'gradereport_user_get_grade_items' => Http::response([
                    'usergrades' => [
                        [
                            'userid' => 35,
                            'gradeitems' => [
                                ['itemtype' => 'course', 'graderaw' => 87, 'gradepass' => 50],
                            ],
                        ],
                        [
                            'userid' => 42,
                            'gradeitems' => [
                                ['itemtype' => 'course', 'graderaw' => 45, 'gradepass' => 50],
                            ],
                        ],
                        [
                            'userid' => 51,
                            'gradeitems' => [
                                ['itemtype' => 'course', 'graderaw' => null, 'gradepass' => 50],
                            ],
                        ],
                    ],
                ]),
                default => Http::response(['exception' => 'moodle_exception', 'errorcode' => 'invalidparameter', 'message' => 'Unknown function'], 200),
            };
        });
    }

    public function test_show_returns_course_details(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

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

    public function test_participants_lists_enrolled_users_with_roles(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->getJson('/api/v1/courses/22/participants')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJson([
                'data' => [
                    [
                        'moodle_user_id' => 35,
                        'fullname' => 'Michael Adeniran',
                        'department' => 'Learning & Development',
                        'roles' => ['student'],
                    ],
                ],
            ]);
    }

    public function test_statistics_aggregates_grades_and_pass_rate(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->getJson('/api/v1/courses/22/statistics')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'enrolled' => 3,
                    'graded' => 2,
                    'average_final_grade' => 66,
                    'highest_grade' => 87,
                    'lowest_grade' => 45,
                    'pass_rate' => 50,
                ],
            ]);
    }

    public function test_completion_report_joins_participants_with_grades(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->getJson('/api/v1/courses/22/completion-report')
            ->assertOk()
            ->assertJson([
                'data' => [
                    ['moodle_user_id' => 35, 'final_grade' => 87, 'status' => 'Passed'],
                    ['moodle_user_id' => 42, 'final_grade' => 45, 'status' => 'Failed'],
                    ['moodle_user_id' => 51, 'final_grade' => null, 'status' => 'Not graded'],
                ],
            ]);
    }
}
