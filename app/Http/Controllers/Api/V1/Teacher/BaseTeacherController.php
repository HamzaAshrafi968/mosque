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
        $teacher = $this->teacherRepository->findByUserId($request->user()->id);

        if (! $teacher) {
            abort(403, 'المعلم غير موجود');
        }

        return $teacher;
    }
}
