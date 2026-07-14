<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\MoodleService;
use App\Support\FieldFilter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CourseController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly MoodleService $moodle)
    {
    }

    public function show(Request $request, int $courseId): JsonResponse
    {
        $course = $this->resolveCourse($courseId);

        return $this->respondSuccess(FieldFilter::apply($request, [
            'id' => $course['id'],
            'short_name' => $course['shortname'] ?? null,
            'full_name' => $course['fullname'] ?? null,
            'category_id' => $course['categoryid'] ?? null,
            'visible' => (bool) ($course['visible'] ?? true),
            'start_date' => isset($course['startdate']) && $course['startdate'] ? date('Y-m-d', $course['startdate']) : null,
            'end_date' => isset($course['enddate']) && $course['enddate'] ? date('Y-m-d', $course['enddate']) : null,
        ]), 'Course retrieved successfully.');
    }

    public function participants(Request $request, int $courseId): JsonResponse
    {
        $this->resolveCourse($courseId);

        $participants = collect($this->moodle->getEnrolledUsers($courseId))->map(fn ($user) => [
            'moodle_user_id' => $user['id'],
            'fullname' => $user['fullname'] ?? null,
            'email' => $user['email'] ?? null,
            'department' => $user['department'] ?? null,
            'roles' => collect($user['roles'] ?? [])->pluck('shortname')->all(),
            'last_access' => isset($user['lastaccess']) && $user['lastaccess'] ? date('Y-m-d', $user['lastaccess']) : null,
        ])->values()->all();

        return $this->respondSuccess(FieldFilter::apply($request, $participants), 'Course participants retrieved successfully.');
    }

    public function statistics(Request $request, int $courseId): JsonResponse
    {
        $this->resolveCourse($courseId);

        $enrolled = count($this->moodle->getEnrolledUsers($courseId));

        $finals = collect($this->moodle->getAllCourseGrades($courseId))
            ->map(fn ($userGrades) => collect($userGrades['gradeitems'] ?? [])->firstWhere('itemtype', 'course'))
            ->filter();

        $graded = $finals->filter(fn ($item) => is_numeric($item['graderaw'] ?? null));
        $passable = $graded->filter(fn ($item) => ($item['gradepass'] ?? 0) > 0);
        $passed = $passable->filter(fn ($item) => $item['graderaw'] >= $item['gradepass']);

        return $this->respondSuccess(FieldFilter::apply($request, [
            'enrolled' => $enrolled,
            'graded' => $graded->count(),
            'average_final_grade' => $graded->isNotEmpty() ? round($graded->avg('graderaw'), 2) : null,
            'highest_grade' => $graded->isNotEmpty() ? (float) $graded->max('graderaw') : null,
            'lowest_grade' => $graded->isNotEmpty() ? (float) $graded->min('graderaw') : null,
            'pass_rate' => $passable->isNotEmpty() ? round(($passed->count() / $passable->count()) * 100, 1) : null,
        ]), 'Course statistics retrieved successfully.');
    }

    public function completionReport(Request $request, int $courseId): JsonResponse
    {
        $this->resolveCourse($courseId);

        $gradesByUser = collect($this->moodle->getAllCourseGrades($courseId))->keyBy('userid');

        $report = collect($this->moodle->getEnrolledUsers($courseId))->map(function ($user) use ($gradesByUser) {
            $final = collect($gradesByUser->get($user['id'])['gradeitems'] ?? [])->firstWhere('itemtype', 'course');
            $grade = $final['graderaw'] ?? null;
            $gradePass = $final['gradepass'] ?? 0;

            return [
                'moodle_user_id' => $user['id'],
                'fullname' => $user['fullname'] ?? null,
                'email' => $user['email'] ?? null,
                'department' => $user['department'] ?? null,
                'final_grade' => is_numeric($grade) ? (float) $grade : null,
                'status' => match (true) {
                    ! is_numeric($grade) => 'Not graded',
                    $gradePass > 0 && $grade >= $gradePass => 'Passed',
                    $gradePass > 0 => 'Failed',
                    default => 'Graded',
                },
            ];
        })->values()->all();

        return $this->respondSuccess(FieldFilter::apply($request, $report), 'Course completion report retrieved successfully.');
    }

    private function resolveCourse(int $courseId): array
    {
        $course = $this->moodle->getCourse($courseId);

        if (! $course) {
            throw new NotFoundHttpException("No course found for id \"{$courseId}\".");
        }

        return $course;
    }
}
