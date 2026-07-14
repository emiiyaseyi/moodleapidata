<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\MoodleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EnrolmentController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly MoodleService $moodle)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'course_id' => ['required', 'integer', 'min:1'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'role_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $this->moodle->findUserByEmail($data['email']);

        if (! $user) {
            throw new NotFoundHttpException("No Moodle user found for email \"{$data['email']}\". The account must exist before enrolment.");
        }

        $start = Carbon::parse($data['start_date'] ?? 'today')->startOfDay();

        $end = match (true) {
            isset($data['end_date']) => Carbon::parse($data['end_date'])->endOfDay(),
            isset($data['duration_days']) => $start->copy()->addDays($data['duration_days'])->endOfDay(),
            default => null,
        };

        if ($end && $end->lte($start)) {
            throw ValidationException::withMessages([
                'end_date' => 'The enrolment end date must be after the start date.',
            ]);
        }

        $this->moodle->enrolUser(
            userId: $user['id'],
            courseId: $data['course_id'],
            roleId: $data['role_id'] ?? (int) config('services.moodle.enrol_role_id', 5),
            timeStart: $start->getTimestamp(),
            timeEnd: $end?->getTimestamp() ?? 0,
        );

        return $this->respondSuccess([
            'moodle_user_id' => $user['id'],
            'email' => $data['email'],
            'course_id' => $data['course_id'],
            'starts_on' => $start->toDateString(),
            'ends_on' => $end?->toDateString(),
        ], 'User enrolled successfully.', 201);
    }
}
