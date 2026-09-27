<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranCompletionStatus;
use App\Enums\QuranReading;
use App\Http\Controllers\Controller;
use App\Models\HafizMonthlyExam;
use App\Models\ProgramEnrollment;
use App\Models\QuranCompletion;
use App\Models\QuranRecitationSession;
use App\Models\Student;
use App\Services\AuthorizationService;
use App\Services\QuranJourneyService;
use App\Support\QuranProgramSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuranProgramController extends Controller
{
    public function __construct(
        private readonly QuranJourneyService $journeys,
        private readonly AuthorizationService $authorization,
    ) {}

    /** نظرة عامة على البرامج القرآنية + مؤشرات تشغيلية. */
    public function index(Request $request): View
    {
        $currentMonth = QuranProgramSettings::monthOf(now());

        $pendingCompletions = QuranCompletion::query()
            ->with('student:id,name')
            ->where('status', QuranCompletionStatus::Pending)
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $activeQualifying = ProgramEnrollment::query()
            ->with('student:id,name')
            ->where('program_type', ProgramType::Qualifying)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->get();

        $activeIjazah = ProgramEnrollment::query()
            ->with('student:id,name')
            ->where('program_type', ProgramType::Ijazah)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->get();

        $activeReadings = ProgramEnrollment::query()
            ->with('student:id,name')
            ->where('program_type', ProgramType::Readings)
            ->where('status', ProgramEnrollmentStatus::Active)
            ->get();

        // Students whose latest tasmee' result needs review.
        $needsReview = $this->studentsByLatestResult('needs_review');

        // Students close to completing the whole Quran (heuristic).
        $closeToCompletion = $this->closeToCompletionStudents();

        $untestedIds = HafizMonthlyExam::query()
            ->where('month', $currentMonth)
            ->where('exam_status', 'not_tested')
            ->pluck('student_id');

        return view('admin.quran.index', [
            'stats' => [
                'hafiz' => QuranCompletion::query()->where('status', QuranCompletionStatus::Confirmed)->distinct('student_id')->count('student_id'),
                'qualifying' => $activeQualifying->count(),
                'ijazah' => $activeIjazah->count(),
                'readings' => $activeReadings->count(),
                'pendingCompletions' => $pendingCompletions->count(),
                'untestedMonth' => $untestedIds->count(),
            ],
            'pendingCompletions' => $pendingCompletions,
            'qualifyingStudents' => $activeQualifying->map(fn ($e) => $e->student)->filter(),
            'ijazahStudents' => $activeIjazah->map(fn ($e) => $e->student)->filter(),
            'readingsStudents' => $activeReadings->map(fn ($e) => $e->student)->filter(),
            'needsReview' => $needsReview,
            'closeToCompletion' => $closeToCompletion,
            'currentMonth' => $currentMonth,
        ]);
    }

    /** رحلة الطالب القرآنية الكاملة. */
    public function journey(Student $student, Request $request): View
    {
        $journey = $this->journeys->journey($student);

        $tasmee = $student->quranRecitationSessions()
            ->with('teacher:id,name')
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->limit(15)
            ->get();

        $weeklyEvaluations = $student->qualifyingWeeklyEvaluations()
            ->with('evaluatedBy:id,name')
            ->orderByDesc('week_start')
            ->get();

        $monthlyEvaluations = $student->ijazahMonthlyEvaluations()
            ->with('evaluatedBy:id,name')
            ->orderByDesc('month')
            ->get();

        $currentMonth = QuranProgramSettings::monthOf(now());
        $currentMonthWeeklyEvaluations = $student->ijazahWeeklyEvaluations()
            ->where('month', $currentMonth)
            ->orderBy('week')
            ->get()
            ->keyBy('week');

        $exams = $student->hafizMonthlyExams()
            ->with(['supervisor:id,name', 'revisions'])
            ->orderByDesc('month')
            ->get();

        return view('admin.quran.journey', [
            'student' => $student,
            'journey' => $journey,
            'tasmee' => $tasmee,
            'weeklyEvaluations' => $weeklyEvaluations,
            'monthlyEvaluations' => $monthlyEvaluations,
            'currentMonth' => $currentMonth,
            'currentMonthWeeklyEvaluations' => $currentMonthWeeklyEvaluations,
            'exams' => $exams,
            'readings' => QuranReading::cases(),
            'enrolledReadings' => $journey['readings']->pluck('reading')->filter()->values()->all(),
            'canEnrollReadings' => $this->authorization->can($request->user(), 'quran_training.update'),
            'monthLabel' => fn (string $m) => QuranProgramSettings::monthLabel($m),
        ]);
    }

    /** @return Collection<int, Student> */
    private function studentsByLatestResult(string $result)
    {
        $candidates = Student::query()
            ->whereHas('quranRecitationSessions', function ($q) use ($result) {
                $q->where('result', $result);
            })
            ->with('classroom:id,name')
            ->get();

        // Latest session per student resolved in ONE query (desc order, first
        // occurrence per student wins) instead of a query per student.
        $latestSessions = QuranRecitationSession::query()
            ->whereIn('student_id', $candidates->pluck('id'))
            ->orderByDesc('date')
            ->orderByDesc('created_at')
            ->get(['id', 'student_id', 'result'])
            ->unique('student_id');

        return $candidates->filter(
            fn (Student $student) => $latestSessions->firstWhere('student_id', $student->id)?->result?->value === $result
        )->values()->take(8);
    }

    private function closeToCompletionStudents()
    {
        // Σ new-memorization pages per student in one grouped query.
        $pageTotals = QuranRecitationSession::query()
            ->where('type', 'new')
            ->selectRaw('student_id, sum(amount) as total')
            ->groupBy('student_id')
            ->havingRaw('sum(amount) >= ?', [QuranProgramSettings::CLOSE_TO_COMPLETION_PAGES])
            ->pluck('total', 'student_id');

        return Student::query()
            ->whereDoesntHave('quranCompletions', fn ($q) => $q->where('status', QuranCompletionStatus::Confirmed))
            ->whereIn('id', $pageTotals->keys())
            ->with('classroom:id,name')
            ->orderBy('name')
            ->take(8)
            ->get();
    }
}
