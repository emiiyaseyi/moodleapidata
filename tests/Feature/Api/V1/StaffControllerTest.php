<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffControllerTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMoodle(): void
    {
        Http::fake(function (HttpRequest $request) {
            $function = $request['wsfunction'] ?? null;

            return match ($function) {
                'core_user_get_users' => Http::response([
                    'users' => [
                        [
                            'id' => 35,
                            'fullname' => 'Michael Adeniran',
                            'email' => 'michael@email.com',
                            'department' => 'Learning & Development',
                            'lastaccess' => 1751500000,
                        ],
                    ],
                ]),
                'core_enrol_get_users_courses' => Http::response([
                    [
                        'id' => 22,
                        'shortname' => 'CX',
                        'fullname' => 'Customer Experience',
                        'progress' => 82.5,
                        'completed' => true,
                        'startdate' => 1750000000,
                        'enddate' => 0,
                        'lastaccess' => 1751400000,
                    ],
                ]),
                'gradereport_overview_get_course_grades' => Http::response([
                    'grades' => [
                        ['courseid' => 22, 'grade' => '87.00', 'rawgrade' => '87'],
                    ],
                ]),
                'core_badges_get_user_badges' => Http::response([
                    'badges' => [
                        [
                            'name' => 'AML Champion',
                            'description' => 'Completed AML training with distinction',
                            'dateissued' => 1751000000,
                            'dateexpire' => 0,
                            'badgeurl' => 'https://moodle.example/badge/1',
                            'uniquehash' => 'abc123hash',
                        ],
                    ],
                ]),
                'core_competency_list_user_plans' => Http::response([
                    [
                        'id' => 7,
                        'name' => 'Compliance Fundamentals',
                        'status' => 1,
                        'duedate' => 1760000000,
                    ],
                ]),
                'core_completion_get_course_completion_status' => Http::response([
                    'completionstatus' => [
                        'completed' => true,
                        'completions' => [
                            ['timecompleted' => 1751000000],
                        ],
                    ],
                ]),
                'core_completion_get_activities_completion_status' => Http::response([
                    'statuses' => [
                        ['state' => 1],
                        ['state' => 1],
                        ['state' => 0],
                    ],
                ]),
                'gradereport_user_get_grade_items' => Http::response([
                    'usergrades' => [
                        [
                            'gradeitems' => [
                                [
                                    'itemname' => 'Final Assessment',
                                    'itemtype' => 'course',
                                    'graderaw' => 87,
                                    'gradeformatted' => '87.00',
                                    'gradepass' => 50,
                                ],
                            ],
                        ],
                    ],
                ]),
                default => Http::response(['exception' => 'moodle_exception', 'errorcode' => 'invalidparameter', 'message' => 'Unknown function'], 200),
            };
        });
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/staff/michael@email.com')
            ->assertUnauthorized();
    }

    public function test_show_returns_staff_profile(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->getJson('/api/v1/staff/michael@email.com')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'moodle_user_id' => 35,
                    'fullname' => 'Michael Adeniran',
                    'email' => 'michael@email.com',
                ],
            ]);
    }

    public function test_show_returns_404_for_unknown_email(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        Http::fake(fn () => Http::response(['users' => []]));

        $this->getJson('/api/v1/staff/nobody@email.com')
            ->assertNotFound()
            ->assertJson(['success' => false]);
    }

    public function test_show_supports_field_selection(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $response = $this->getJson('/api/v1/staff/michael@email.com?fields=email');

        $response->assertOk();
        $this->assertSame(['email' => 'michael@email.com'], $response->json('data'));
    }

    public function test_courses_returns_enrolled_courses(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->getJson('/api/v1/staff/michael@email.com/courses')
            ->assertOk()
            ->assertJsonFragment(['fullname' => 'Customer Experience']);
    }

    public function test_course_progress_is_calculated_from_activity_completion(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->getJson('/api/v1/staff/michael@email.com/courses/22/progress')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'progress' => 67,
                    'completed' => false,
                    'activities_completed' => 2,
                    'activities_remaining' => 1,
                ],
            ]);
    }

    public function test_course_grades_summarizes_final_grade_and_status(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->getJson('/api/v1/staff/michael@email.com/courses/22/grades')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'final_grade' => 87,
                    'status' => 'Passed',
                ],
            ]);
    }

    public function test_course_completion_returns_completion_date(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->getJson('/api/v1/staff/michael@email.com/courses/22/completion')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'completed' => true,
                ],
            ]);
    }

    public function test_summary_aggregates_courses_and_average_score(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->getJson('/api/v1/staff/michael@email.com/summary')
            ->assertOk()
            ->assertJson([
                'data' => [
                    'courses' => 1,
                    'completed' => 1,
                    'in_progress' => 0,
                    'average_score' => 87.0,
                ],
            ]);
    }

    public function test_transcript_joins_courses_with_overview_grades(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->getJson('/api/v1/staff/michael@email.com/transcript')
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'course_id' => 22,
                        'full_name' => 'Customer Experience',
                        'progress' => 83,
                        'completed' => true,
                        'grade' => '87.00',
                        'end_date' => null,
                    ],
                ],
            ]);
    }

    public function test_badges_returns_issued_badges(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->getJson('/api/v1/staff/michael@email.com/badges')
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'name' => 'AML Champion',
                        'expires_on' => null,
                        'verification_hash' => 'abc123hash',
                    ],
                ],
            ]);
    }

    public function test_competencies_returns_learning_plans_with_readable_status(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->getJson('/api/v1/staff/michael@email.com/competencies')
            ->assertOk()
            ->assertJson([
                'data' => [
                    [
                        'id' => 7,
                        'name' => 'Compliance Fundamentals',
                        'status' => 'Active',
                    ],
                ],
            ]);
    }

    public function test_moodle_error_is_translated_to_json_envelope(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        Http::fake(fn () => Http::response([
            'exception' => 'moodle_exception',
            'errorcode' => 'invalidtoken',
            'message' => 'Invalid token - token not found',
        ]));

        $this->getJson('/api/v1/staff/michael@email.com')
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }
}
