<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\MoodleApiException;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\MoodleService;
use App\Support\FieldFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class StaffController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly MoodleService $moodle)
    {
    }

    public function show(Request $request, string $email): JsonResponse
    {
        $user = $this->resolveUser($email);

        return $this->respondSuccess(FieldFilter::apply($request, [
            'moodle_user_id' => $user['id'],
            'fullname' => $user['fullname'] ?? null,
            'email' => $user['email'] ?? null,
            'department' => $user['department'] ?? null,
        ]), 'Staff member retrieved successfully.');
    }

    public function courses(Request $request, string $email): JsonResponse
    {
        $user = $this->resolveUser($email);
        $courses = $this->moodle->getUserCourses($user['id']);

        return $this->respondSuccess(FieldFilter::apply($request, $courses), 'Staff courses retrieved successfully.');
    }

    public function courseProgress(Request $request, string $email, int $courseId): JsonResponse
    {
        $user = $this->resolveUser($email);
        $statuses = $this->moodle->getCourseActivityCompletion($user['id'], $courseId);

        return $this->respondSuccess(FieldFilter::apply($request, $this->summarizeProgress($statuses)), 'Course progress retrieved successfully.');
    }

    public function courseGrades(Request $request, string $email, int $courseId): JsonResponse
    {
        $user = $this->resolveUser($email);
        $grades = $this->moodle->getCourseGrades($courseId, $user['id']);

        return $this->respondSuccess(FieldFilter::apply($request, $this->summarizeGrades($grades)), 'Course grades retrieved successfully.');
    }

    public function courseCompletion(Request $request, string $email, int $courseId): JsonResponse
    {
        $user = $this->resolveUser($email);
        $completion = $this->moodle->getCourseCompletion($user['id'], $courseId)['completionstatus'] ?? [];

        return $this->respondSuccess(FieldFilter::apply($request, [
            'completed' => (bool) ($completion['completed'] ?? false),
            'completion_date' => isset($completion['completions'][0]['timecompleted']) && $completion['completions'][0]['timecompleted']
                ? date('Y-m-d', $completion['completions'][0]['timecompleted'])
                : null,
        ]), 'Course completion retrieved successfully.');
    }

    public function summary(Request $request, string $email): JsonResponse
    {
        $user = $this->resolveUser($email);
        $courses = $this->moodle->getUserCourses($user['id']);

        $completed = 0;
        $inProgress = 0;
        $scores = [];

        foreach ($courses as $course) {
            $completion = $this->moodle->getCourseCompletion($user['id'], $course['id'])['completionstatus'] ?? [];
            $isCompleted = (bool) ($completion['completed'] ?? false);

            $isCompleted ? $completed++ : $inProgress++;

            $grades = $this->moodle->getCourseGrades($course['id'], $user['id']);
            $finalGrade = collect($grades['gradeitems'] ?? [])
                ->firstWhere('itemtype', 'course')['graderaw'] ?? null;

            if (is_numeric($finalGrade)) {
                $scores[] = (float) $finalGrade;
            }
        }

        return $this->respondSuccess(FieldFilter::apply($request, [
            'staff' => [
                'id' => $user['id'],
                'name' => $user['fullname'] ?? null,
            ],
            'courses' => count($courses),
            'completed' => $completed,
            'in_progress' => $inProgress,
            'average_score' => $scores !== [] ? round(array_sum($scores) / count($scores), 2) : null,
            'last_login' => isset($user['lastaccess']) && $user['lastaccess']
                ? date('Y-m-d', $user['lastaccess'])
                : null,
        ]), 'Staff summary retrieved successfully.');
    }

    public function transcript(Request $request, string $email): JsonResponse
    {
        $user = $this->resolveUser($email);
        $courses = $this->moodle->getUserCourses($user['id']);
        $grades = collect($this->moodle->getOverviewGrades($user['id']))->keyBy('courseid');

        $transcript = collect($courses)->map(function ($course) use ($grades) {
            $grade = $grades->get($course['id'], []);

            return [
                'course_id' => $course['id'],
                'short_name' => $course['shortname'] ?? null,
                'full_name' => $course['fullname'] ?? null,
                'progress' => isset($course['progress']) ? (int) round($course['progress']) : null,
                'completed' => (bool) ($course['completed'] ?? false),
                'grade' => $grade['grade'] ?? null,
                'grade_raw' => $grade['rawgrade'] ?? null,
                'start_date' => isset($course['startdate']) && $course['startdate'] ? date('Y-m-d', $course['startdate']) : null,
                'end_date' => isset($course['enddate']) && $course['enddate'] ? date('Y-m-d', $course['enddate']) : null,
                'last_access' => isset($course['lastaccess']) && $course['lastaccess'] ? date('Y-m-d', $course['lastaccess']) : null,
            ];
        })->values()->all();

        return $this->respondSuccess(FieldFilter::apply($request, $transcript), 'Staff transcript retrieved successfully.');
    }

    public function badges(Request $request, string $email): JsonResponse
    {
        $user = $this->resolveUser($email);

        $badges = collect($this->moodle->getUserBadges($user['id']))->map(fn ($badge) => [
            'name' => $badge['name'] ?? null,
            'description' => $badge['description'] ?? null,
            'issued_on' => isset($badge['dateissued']) && $badge['dateissued'] ? date('Y-m-d', $badge['dateissued']) : null,
            'expires_on' => isset($badge['dateexpire']) && $badge['dateexpire'] ? date('Y-m-d', $badge['dateexpire']) : null,
            'badge_url' => $badge['badgeurl'] ?? null,
            'verification_hash' => $badge['uniquehash'] ?? null,
        ])->values()->all();

        return $this->respondSuccess(FieldFilter::apply($request, $badges), 'Staff badges retrieved successfully.');
    }

    public function competencies(Request $request, string $email): JsonResponse
    {
        $user = $this->resolveUser($email);

        $plans = collect($this->moodle->getUserLearningPlans($user['id']))->map(fn ($plan) => [
            'id' => $plan['id'] ?? null,
            'name' => $plan['name'] ?? null,
            'status' => match ($plan['status'] ?? null) {
                0 => 'Draft',
                1 => 'Active',
                2 => 'Complete',
                3 => 'Waiting for review',
                4 => 'In review',
                default => null,
            },
            'due_date' => isset($plan['duedate']) && $plan['duedate'] ? date('Y-m-d', $plan['duedate']) : null,
        ])->values()->all();

        return $this->respondSuccess(FieldFilter::apply($request, $plans), 'Staff learning plans retrieved successfully.');
    }

    private function resolveUser(string $email): array
    {
        $user = $this->moodle->findUserByEmail($email);

        if (! $user) {
            throw new NotFoundHttpException("No staff member found for email \"{$email}\".");
        }

        return $user;
    }

    private function summarizeProgress(array $statuses): array
    {
        $total = count($statuses);
        $completed = collect($statuses)->filter(fn ($s) => ($s['state'] ?? 0) >= 1)->count();

        return [
            'progress' => $total > 0 ? (int) round(($completed / $total) * 100) : 0,
            'completed' => $total > 0 && $completed === $total,
            'activities_completed' => $completed,
            'activities_remaining' => $total - $completed,
        ];
    }

    private function summarizeGrades(array $grades): array
    {
        $items = collect($grades['gradeitems'] ?? []);
        $courseItem = $items->firstWhere('itemtype', 'course');

        return [
            'items' => $items->map(fn ($item) => [
                'name' => $item['itemname'] ?? null,
                'grade' => $item['graderaw'] ?? null,
                'grade_formatted' => $item['gradeformatted'] ?? null,
            ])->values()->all(),
            'final_grade' => $courseItem['graderaw'] ?? null,
            'status' => isset($courseItem['graderaw'])
                ? (($courseItem['graderaw'] >= ($courseItem['gradepass'] ?? 0)) ? 'Passed' : 'Failed')
                : null,
        ];
    }
}
