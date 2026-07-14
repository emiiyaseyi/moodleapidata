<?php

namespace App\Services;

use App\Exceptions\MoodleApiException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class MoodleService
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $token,
        private readonly int $cacheTtl,
    ) {
    }

    public function findUserByEmail(string $email): ?array
    {
        return $this->remember("user:email:{$email}", function () use ($email) {
            $response = $this->call('core_user_get_users', [
                'criteria' => [
                    ['key' => 'email', 'value' => $email],
                ],
            ]);

            return $response['users'][0] ?? null;
        });
    }

    public function findUserById(int $userId): ?array
    {
        return $this->remember("user:id:{$userId}", function () use ($userId) {
            $response = $this->call('core_user_get_users', [
                'criteria' => [
                    ['key' => 'id', 'value' => $userId],
                ],
            ]);

            return $response['users'][0] ?? null;
        });
    }

    public function getUserCourses(int $userId): array
    {
        return $this->remember("user:{$userId}:courses", fn () => $this->call('core_enrol_get_users_courses', [
            'userid' => $userId,
        ]));
    }

    public function getCourse(int $courseId): ?array
    {
        return $this->remember("course:{$courseId}", function () use ($courseId) {
            $response = $this->call('core_course_get_courses', [
                'options' => [
                    'ids' => [$courseId],
                ],
            ]);

            return $response[0] ?? null;
        });
    }

    public function getCourseCompletion(int $userId, int $courseId): array
    {
        return $this->remember("completion:{$userId}:{$courseId}", fn () => $this->call('core_completion_get_course_completion_status', [
            'userid' => $userId,
            'courseid' => $courseId,
        ]));
    }

    public function getCourseActivityCompletion(int $userId, int $courseId): array
    {
        return $this->remember("activity_completion:{$userId}:{$courseId}", function () use ($userId, $courseId) {
            $response = $this->call('core_completion_get_activities_completion_status', [
                'userid' => $userId,
                'courseid' => $courseId,
            ]);

            return $response['statuses'] ?? [];
        });
    }

    public function getCourseGrades(int $courseId, int $userId): array
    {
        return $this->remember("grades:{$courseId}:{$userId}", function () use ($courseId, $userId) {
            $response = $this->call('gradereport_user_get_grade_items', [
                'courseid' => $courseId,
                'userid' => $userId,
            ]);

            return $response['usergrades'][0] ?? [];
        });
    }

    /**
     * Final grade for every course the user is enrolled in, in one call
     * (gradereport_overview), keyed as returned by Moodle.
     */
    public function getOverviewGrades(int $userId): array
    {
        return $this->remember("overview_grades:{$userId}", function () use ($userId) {
            $response = $this->call('gradereport_overview_get_course_grades', [
                'userid' => $userId,
            ]);

            return $response['grades'] ?? [];
        });
    }

    public function getUserBadges(int $userId): array
    {
        return $this->remember("badges:{$userId}", function () use ($userId) {
            $response = $this->call('core_badges_get_user_badges', [
                'userid' => $userId,
            ]);

            return $response['badges'] ?? [];
        });
    }

    public function getEnrolledUsers(int $courseId): array
    {
        return $this->remember("course:{$courseId}:participants", fn () => $this->call('core_enrol_get_enrolled_users', [
            'courseid' => $courseId,
        ]));
    }

    /**
     * Grade report for every user in the course (userid omitted = all users;
     * requires the service account to hold moodle/grade:viewall in the course).
     */
    public function getAllCourseGrades(int $courseId): array
    {
        return $this->remember("grades:{$courseId}:all", function () use ($courseId) {
            $response = $this->call('gradereport_user_get_grade_items', [
                'courseid' => $courseId,
            ]);

            return $response['usergrades'] ?? [];
        });
    }

    public function getUserLearningPlans(int $userId): array
    {
        return $this->remember("plans:{$userId}", fn () => $this->call('core_competency_list_user_plans', [
            'userid' => $userId,
        ]));
    }

    /**
     * Manually enrol a user into a course (enrol_manual_enrol_users).
     * timeStart/timeEnd are unix timestamps; 0 means no limit. Re-enrolling
     * an already-enrolled user simply updates their enrolment window.
     */
    public function enrolUser(int $userId, int $courseId, int $roleId, int $timeStart = 0, int $timeEnd = 0): void
    {
        $this->call('enrol_manual_enrol_users', [
            'enrolments' => [[
                'roleid' => $roleId,
                'userid' => $userId,
                'courseid' => $courseId,
                'timestart' => $timeStart,
                'timeend' => $timeEnd,
            ]],
        ]);

        // The enrolment changes what several cached reads would return.
        Cache::forget("moodle:user:{$userId}:courses");
        Cache::forget("moodle:overview_grades:{$userId}");
        Cache::forget("moodle:course:{$courseId}:participants");
        Cache::forget("moodle:grades:{$courseId}:all");
    }

    private function remember(string $key, \Closure $resolver): mixed
    {
        return Cache::remember("moodle:{$key}", $this->cacheTtl, $resolver);
    }

    private function call(string $wsfunction, array $params = []): array
    {
        try {
            $response = Http::asForm()->post("{$this->baseUrl}/webservice/rest/server.php", array_merge([
                'wstoken' => $this->token,
                'wsfunction' => $wsfunction,
                'moodlewsrestformat' => 'json',
            ], $this->flatten($params)));
        } catch (Throwable $e) {
            throw MoodleApiException::connectionFailed($e->getMessage());
        }

        if ($response->failed()) {
            throw MoodleApiException::connectionFailed("HTTP {$response->status()}");
        }

        $data = $response->json();

        if (is_array($data) && isset($data['exception'])) {
            throw MoodleApiException::fromMoodleResponse($data);
        }

        return $data ?? [];
    }

    /**
     * Moodle's REST endpoint expects nested arrays as bracketed form keys
     * (e.g. criteria[0][key]=email), so we flatten PHP arrays into that shape.
     */
    private function flatten(array $params, string $prefix = ''): array
    {
        $flat = [];

        foreach ($params as $key => $value) {
            $flatKey = $prefix === '' ? $key : "{$prefix}[{$key}]";

            if (is_array($value)) {
                $flat += $this->flatten($value, $flatKey);
            } else {
                $flat[$flatKey] = $value;
            }
        }

        return $flat;
    }
}
