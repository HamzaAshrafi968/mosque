<?php

namespace App\Http\Controllers\Teacher;

use App\Models\Section;
use App\Services\AttendanceMetricService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "My sections" (spec §19-§22): the teacher only ever sees sections assigned
 * to them (explicit assignments with timetable fallback). Students listed are
 * the current roster of that section with attendance derived from the records.
 */
class SectionController extends BaseTeacherController
{
    public function index(Request $request): View
    {
        $sections = $this->manageableSections($request)
            ->loadCount(['students' => fn ($q) => $q->active(), 'attendanceSessions']);

        return view('teacher.sections.index', [
            'sections' => $sections,
        ]);
    }

    public function show(Request $request, Section $section): View
    {
        $this->assertCanManageSection($request, $section);
        $section->load(['classroom:id,name']);

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        return view('teacher.sections.show', [
            'section' => $section,
            'roster' => app(AttendanceMetricService::class)->rosterStats($section, $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }
}
