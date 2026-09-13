<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranTasmeeResult;
use App\Models\FaithMeetingStudent;
use App\Models\HafizExamRevision;
use App\Models\HafizMonthlyExam;
use App\Models\ProgramEnrollment;
use App\Models\Student;
use App\Services\QuranJourneyService;
use App\Services\QuranProgramService;
use App\Services\QuranScopeService;
use App\Support\QuranProgramSettings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuranProgramController extends BaseTeacherController
{
    public function __construct(
        private readonly QuranScopeService $scope,
        private readonly QuranJourneyService $journeys,
        private readonly QuranProgramService $programs,
    ) {}

    /** نظرة عامة + مؤشرات للشيخ/المعلم. */
    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);
        $studentIds = $this->scope->studentIdsFor($teacher);

        $currentMonth = QuranProgramSettings::monthOf(now());

        $students = Student::query()->whereIn('id', $studentIds)->get();

        $myTasmee = $teacher->quranRecitationSessions();
        $tasmeeStats = [
            'new' => (clone $myTasmee)->where('type', 'new')->count(),
            'revision' => (clone $myTasmee)->where('type', 'revision')->count(),
            'newThisWeek' => (clone $myTasmee)->where('type', 'new')->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])->sum('amount'),
            'revisionThisWeek' => (clone $myTasmee)->where('type', 'revision')->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])->sum('amount'),
        ];

        $qualifyingIds = ProgramEnrollment::query()
            ->where('program_type', ProgramType::Qualifying)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id');

        $ijazahIds = ProgramEnrollment::query()
            ->where('program_type', ProgramType::Ijazah)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->whereIn('student_id', $studentIds)
            ->pluck('student_id');

        $untestedMonth = HafizMonthlyExam::query()
            ->where('month', $currentMonth)
            ->where('exam_status', 'not_tested')
            ->whereIn('student_id', $studentIds)
            ->where(fn ($q) => $q->whereNull('supervisor_id')->orWhere('supervisor_id', $teacher->id))
            ->get();

        $weakStudents = $students->filter(function (Student $student) {
            $latest = $student->quranRecitationSessions()->orderByDesc('date')->orderByDesc('created_at')->first();

            return $latest?->result === QuranTasmeeResult::NeedsReview;
        });

        $pendingRevisions = HafizExamRevision::query()
            ->where('status', 'pending')
            ->whereHas('exam', fn ($q) => $q->whereIn('student_id', $studentIds)
                ->where(fn ($q2) => $q2->whereNull('supervisor_id')->orWhere('supervisor_id', $teacher->id)))
            ->with(['exam.student:id,name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $myMeetings = $this->scope->meetingsQuery($teacher)
            ->withCount('studentAttendances')
            ->where('status', 'scheduled')
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->limit(10)
            ->get();

        $studentAttendancePending = FaithMeetingStudent::query()
            ->whereHas('meeting', fn ($q) => $q
                ->where('status', 'scheduled')
                ->where('date', '<=', now()->toDateString())
                ->where(fn ($q2) => $q2->where('supervisor_id', $teacher->id)->orWhere('teacher_id', $teacher->id)))
            ->whereNull('attendance_status')
            ->count();

        return view('teacher.quran.index', [
            'myStudentCount' => $students->count(),
            'tasmeeStats' => $tasmeeStats,
            'qualifyingIds' => $qualifyingIds,
            'ijazahIds' => $ijazahIds,
            'qualifyingStudents' => $students->whereIn('id', $qualifyingIds)->take(8),
            'ijazahStudents' => $students->whereIn('id', $ijazahIds)->take(8),
            'untestedMonth' => $untestedMonth,
            'weakStudents' => $weakStudents->take(8),
            'pendingRevisions' => $pendingRevisions,
            'myMeetings' => $myMeetings,
            'studentAttendancePending' => $studentAttendancePending,
            'currentMonth' => $currentMonth,
            'monthLabel' => fn (string $m) => QuranProgramSettings::monthLabel($m),
        ]);
    }

    /** رحلة طالب قرآنية (ضمن نطاق المعلم فقط). */
    public function journey(Request $request, Student $student): View
    {
        $teacher = $this->currentTeacher($request);
        $this->scope->assertCanManageStudent($teacher, $student);

        $journey = $this->journeys->journey($student);

        $currentMonth = QuranProgramSettings::monthOf(now());

        return view('teacher.quran.journey', [
            'student' => $student,
            'journey' => $journey,
            'tasmee' => $student->quranRecitationSessions()
                ->with('teacher:id,name')
                ->orderByDesc('date')
                ->orderByDesc('created_at')
                ->limit(15)
                ->get(),
            'weeklyEvaluations' => $student->qualifyingWeeklyEvaluations()
                ->with('evaluatedBy:id,name')
                ->orderByDesc('week_start')
                ->get(),
            'monthlyEvaluations' => $student->ijazahMonthlyEvaluations()
                ->with('evaluatedBy:id,name')
                ->orderByDesc('month')
                ->get(),
            'currentMonth' => $currentMonth,
            'currentMonthWeeklyEvaluations' => $student->ijazahWeeklyEvaluations()
                ->where('month', $currentMonth)
                ->orderBy('week')
                ->get()
                ->keyBy('week'),
            'exams' => $student->hafizMonthlyExams()
                ->with(['supervisor:id,name', 'revisions'])
                ->orderByDesc('month')
                ->get(),
            'monthLabel' => fn (string $m) => QuranProgramSettings::monthLabel($m),
        ]);
    }
}
