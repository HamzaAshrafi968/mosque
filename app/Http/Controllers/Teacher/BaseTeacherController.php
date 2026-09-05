<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Section;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

abstract class BaseTeacherController extends Controller
{
    protected function currentTeacher(Request $request): Teacher
    {
        return Teacher::where('user_id', $request->user()->id)->firstOrFail();
    }

    /**
     * Sections the teacher may manage: explicit section assignments
     * (section_teachers) with timetable rows as a fallback for legacy data.
     * A teacher never gains access to sections outside this scope.
     */
    protected function manageableSections(Request $request): Collection
    {
        $teacher = $this->currentTeacher($request);

        return Section::query()
            ->with('classroom:id,name')
            ->active()
            ->whereIn('id', $teacher->manageableSectionIds())
            ->orderBy('name')
            ->get();
    }

    protected function assertCanManageSection(Request $request, Section $section): void
    {
        if (! $this->manageableSections($request)->contains('id', $section->id)) {
            abort(403, 'لا تملك صلاحية الوصول لهذه الشعبة');
        }
    }
}
