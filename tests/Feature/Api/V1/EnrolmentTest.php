<?php

namespace Tests\Feature\Api\V1;

use App\Models\ApiConsumer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnrolmentTest extends TestCase
{
    use RefreshDatabase;

    private function fakeMoodle(): void
    {
        Http::fake(function (HttpRequest $request) {
            return match ($request['wsfunction'] ?? null) {
                'core_user_get_users' => Http::response([
                    'users' => [
                        ['id' => 35, 'fullname' => 'Michael Adeniran', 'email' => 'michael@email.com'],
                    ],
                ]),
                'enrol_manual_enrol_users' => Http::response(null),
                default => Http::response(['exception' => 'moodle_exception', 'errorcode' => 'invalidparameter', 'message' => 'Unknown function'], 200),
            };
        });
    }

    public function test_enrols_user_with_duration_window(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->postJson('/api/v1/enrolments', [
            'email' => 'michael@email.com',
            'course_id' => 22,
            'start_date' => '2026-08-01',
            'duration_days' => 14,
        ])
            ->assertCreated()
            ->assertJson([
                'success' => true,
                'data' => [
                    'moodle_user_id' => 35,
                    'course_id' => 22,
                    'starts_on' => '2026-08-01',
                    'ends_on' => '2026-08-15',
                ],
            ]);

        $expectedStart = Carbon::parse('2026-08-01')->startOfDay()->getTimestamp();
        $expectedEnd = Carbon::parse('2026-08-15')->endOfDay()->getTimestamp();

        Http::assertSent(function (HttpRequest $request) use ($expectedStart, $expectedEnd) {
            return $request['wsfunction'] === 'enrol_manual_enrol_users'
                && (int) $request['enrolments[0][userid]'] === 35
                && (int) $request['enrolments[0][courseid]'] === 22
                && (int) $request['enrolments[0][roleid]'] === 5
                && (int) $request['enrolments[0][timestart]'] === $expectedStart
                && (int) $request['enrolments[0][timeend]'] === $expectedEnd;
        });
    }

    public function test_enrolment_without_end_is_open_ended(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->postJson('/api/v1/enrolments', [
            'email' => 'michael@email.com',
            'course_id' => 22,
        ])
            ->assertCreated()
            ->assertJson(['data' => ['ends_on' => null]]);

        Http::assertSent(fn (HttpRequest $request) => $request['wsfunction'] === 'enrol_manual_enrol_users'
            && (int) $request['enrolments[0][timeend]'] === 0);
    }

    public function test_unknown_email_returns_404_and_does_not_enrol(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        Http::fake(fn () => Http::response(['users' => []]));

        $this->postJson('/api/v1/enrolments', [
            'email' => 'nobody@email.com',
            'course_id' => 22,
        ])
            ->assertNotFound()
            ->assertJson(['success' => false]);

        Http::assertNotSent(fn (HttpRequest $request) => $request['wsfunction'] === 'enrol_manual_enrol_users');
    }

    public function test_validation_errors_are_reported_in_envelope(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());

        $response = $this->postJson('/api/v1/enrolments', ['email' => 'not-an-email']);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertArrayHasKey('course_id', $response->json('meta.errors'));
    }

    public function test_end_before_start_is_rejected(): void
    {
        Sanctum::actingAs(ApiConsumer::factory()->create());
        $this->fakeMoodle();

        $this->postJson('/api/v1/enrolments', [
            'email' => 'michael@email.com',
            'course_id' => 22,
            'start_date' => '2026-08-15',
            'end_date' => '2026-08-01',
        ])->assertStatus(422);

        Http::assertNotSent(fn (HttpRequest $request) => $request['wsfunction'] === 'enrol_manual_enrol_users');
    }
}
