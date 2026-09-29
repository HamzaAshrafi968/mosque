<?php

namespace App\Http\Controllers\Api\V1\Teacher;

use App\Contracts\Repositories\TeacherRepositoryInterface;
use App\Http\Controllers\Api\BaseApiController;
use App\Models\Teacher;
use Illuminate\Http\Request;

class BaseTeacherController extends BaseApiController
{
    public function __construct(
        protected readonly TeacherRepositoryInterface $teacherRepository,
    ) {}

    protected function currentTeacher(Request $request): Teacher
    {
        $user = $request->user();

        $teacher = $this->teacherRepository->findByUserId($user->id);

        // يُنشأ الملف تلقائياً عند غيابه حتى لا يرى الأستاذ 403 بعد الدخول
        // (حسابات أُنشئت من شاشة المستخدمين دون ملف أستاذ).
        return $teacher ?? $user->ensureTeacherProfile();
    }
}
