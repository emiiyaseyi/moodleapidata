<?php

namespace Tests\Unit\Services;

use App\Exceptions\MoodleApiException;
use App\Services\MoodleService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MoodleServiceTest extends TestCase
{
    private function service(): MoodleService
    {
        return new MoodleService(
            baseUrl: 'https://moodle.example.test',
            token: 'test-token',
            cacheTtl: 900,
        );
    }

    public function test_find_user_by_email_flattens_criteria_into_moodle_form_params(): void
    {
        Http::fake([
            '*' => Http::response(['users' => [['id' => 35, 'email' => 'michael@email.com']]]),
        ]);

        $user = $this->service()->findUserByEmail('michael@email.com');

        $this->assertSame(35, $user['id']);

        Http::assertSent(function ($request) {
            return $request['wsfunction'] === 'core_user_get_users'
                && $request['wstoken'] === 'test-token'
                && $request['criteria[0][key]'] === 'email'
                && $request['criteria[0][value]'] === 'michael@email.com';
        });
    }

    public function test_find_user_by_email_returns_null_when_no_match(): void
    {
        Http::fake(['*' => Http::response(['users' => []])]);

        $this->assertNull($this->service()->findUserByEmail('nobody@email.com'));
    }

    public function test_moodle_error_response_throws_typed_exception(): void
    {
        Http::fake(['*' => Http::response([
            'exception' => 'moodle_exception',
            'errorcode' => 'invalidtoken',
            'message' => 'Invalid token - token not found',
        ])]);

        $this->expectException(MoodleApiException::class);
        $this->expectExceptionMessage('Invalid token - token not found');

        $this->service()->findUserByEmail('michael@email.com');
    }

    public function test_error_code_maps_to_expected_http_status(): void
    {
        Http::fake(['*' => Http::response([
            'exception' => 'moodle_exception',
            'errorcode' => 'nopermissions',
            'message' => 'No permissions',
        ])]);

        try {
            $this->service()->findUserByEmail('michael@email.com');
            $this->fail('Expected MoodleApiException was not thrown.');
        } catch (MoodleApiException $e) {
            $this->assertSame(403, $e->status);
            $this->assertSame('nopermissions', $e->moodleErrorCode);
        }
    }

    public function test_responses_are_cached_and_do_not_repeat_the_http_call(): void
    {
        Http::fake(['*' => Http::response(['users' => [['id' => 35]]])]);

        $service = $this->service();
        $service->findUserByEmail('michael@email.com');
        $service->findUserByEmail('michael@email.com');

        Http::assertSentCount(1);
    }
}
