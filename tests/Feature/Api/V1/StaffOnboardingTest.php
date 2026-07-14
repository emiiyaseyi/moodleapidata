<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiConsumer;
use App\Models\StaffMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMoodle(): void
    {
        Http::fake(function (HttpRequest $request) {
            return match ($request['wsfunction'] ?? null) {
                'core_user_get_users' => Http::response([
                    'users' => [
                        [
                            'id' => 35,
                            'fullname' => 'Michael Adeniran',
                            'email' => 'michael@email.com',
                            'department' => 'Learning & Development',
                        ],
                    ],
                ]),
                'enrol_manual_enrol_users' => Http::response(null),
                default => Http::response(['exception' => 'moodle_exception', 'errorcode' => 'invalidparameter', 'message' => 'Unknown function'], 200),
            };
        });
    }

    public function test_onboarding_computes_neo_exam_date_and_enrols_into_neo_course(): void
    {
        config(['services.moodle.neo_course_id' => 99]);
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->postJson('/api/v1/staff', [
            'email' => 'michael@email.com',
            'join_date' => '2026-03-10',
        ])
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => [
                    'moodle_user_id' => 35,
                    'department' => 'Learning & Development',
                    'join_date' => '2026-03-10',
                    'neo_exam_date' => '2026-07-10',
                    'neo_enrolment' => [
                        'course_id' => 99,
                        'starts_on' => '2026-03-10',
                        'ends_on' => '2026-07-10',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('staff_members', [
            'email' => 'michael@email.com',
            'moodle_user_id' => 35,
            'neo_exam_date' => '2026-07-10 00:00:00',
        ]);

        $expectedStart = Carbon::parse('2026-03-10')->startOfDay()->getTimestamp();
        $expectedEnd = Carbon::parse('2026-07-10')->endOfDay()->getTimestamp();

        Http::assertSent(function (HttpRequest $request) use ($expectedStart, $expectedEnd) {
            return $request['wsfunction'] === 'enrol_manual_enrol_users'
                && (int) $request['enrolments[0][userid]'] === 35
                && (int) $request['enrolments[0][courseid]'] === 99
                && (int) $request['enrolments[0][timestart]'] === $expectedStart
                && (int) $request['enrolments[0][timeend]'] === $expectedEnd;
        });
    }

    public function test_onboarding_without_neo_course_configured_stores_record_only(): void
    {
        config(['services.moodle.neo_course_id' => null]);
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->postJson('/api/v1/staff', [
            'email' => 'michael@email.com',
            'join_date' => '2026-03-10',
        ])
            ->assertCreated()
            ->assertJson(['data' => ['neo_enrolment' => null]]);

        $this->assertDatabaseCount('staff_members', 1);

        Http::assertNotSent(fn (HttpRequest $request) => $request['wsfunction'] === 'enrol_manual_enrol_users');
    }

    public function test_repushing_the_same_staff_member_updates_instead_of_duplicating(): void
    {
        config(['services.moodle.neo_course_id' => null]);
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->postJson('/api/v1/staff', ['email' => 'michael@email.com', 'join_date' => '2026-03-10'])->assertCreated();
        $this->postJson('/api/v1/staff', ['email' => 'michael@email.com', 'join_date' => '2026-04-01'])->assertCreated();

        $this->assertDatabaseCount('staff_members', 1);
        $this->assertSame('2026-08-01', StaffMember::first()->neo_exam_date->toDateString());
    }

    public function test_unknown_email_returns_404_and_stores_nothing(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        Http::fake(fn () => Http::response(['users' => []]));

        $this->postJson('/api/v1/staff', [
            'email' => 'nobody@email.com',
            'join_date' => '2026-03-10',
        ])->assertNotFound();

        $this->assertDatabaseCount('staff_members', 0);
    }

    public function test_join_date_is_required(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());

        $response = $this->postJson('/api/v1/staff', ['email' => 'michael@email.com']);

        $response->assertStatus(422);
        $this->assertArrayHasKey('join_date', $response->json('meta.errors'));
    }
}
