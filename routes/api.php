<?php

use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\EnrolmentController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\StaffController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', HealthController::class);

    Route::middleware(['auth:sanctum', 'throttle:api', 'log.api'])->group(function () {
        Route::post('/staff', [StaffController::class, 'store']);
        Route::post('/enrolments', [EnrolmentController::class, 'store']);

        Route::get('/staff/{email}', [StaffController::class, 'show']);
        Route::get('/staff/{email}/courses', [StaffController::class, 'courses']);
        Route::get('/staff/{email}/summary', [StaffController::class, 'summary']);
        Route::get('/staff/{email}/transcript', [StaffController::class, 'transcript']);
        Route::get('/staff/{email}/badges', [StaffController::class, 'badges']);
        Route::get('/staff/{email}/competencies', [StaffController::class, 'competencies']);
        Route::get('/staff/{email}/courses/{courseId}/progress', [StaffController::class, 'courseProgress'])->whereNumber('courseId');
        Route::get('/staff/{email}/courses/{courseId}/grades', [StaffController::class, 'courseGrades'])->whereNumber('courseId');
        Route::get('/staff/{email}/courses/{courseId}/completion', [StaffController::class, 'courseCompletion'])->whereNumber('courseId');

        Route::get('/courses/{courseId}', [CourseController::class, 'show'])->whereNumber('courseId');
        Route::get('/courses/{courseId}/participants', [CourseController::class, 'participants'])->whereNumber('courseId');
        Route::get('/courses/{courseId}/statistics', [CourseController::class, 'statistics'])->whereNumber('courseId');
        Route::get('/courses/{courseId}/completion-report', [CourseController::class, 'completionReport'])->whereNumber('courseId');
    });
});
