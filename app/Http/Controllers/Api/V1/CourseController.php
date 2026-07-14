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
        $course = $this->moodle->getCourse($courseId);

        if (! $course) {
            throw new NotFoundHttpException("No course found for id \"{$courseId}\".");
        }

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
}
