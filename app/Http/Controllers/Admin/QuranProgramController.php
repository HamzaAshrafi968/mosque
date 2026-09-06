<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProgramEnrollmentStatus;
use App\Enums\ProgramType;
use App\Enums\QuranCompletionStatus;
use App\Http\Controllers\Controller;
use App\Models\HafizMonthlyExam;
use App\Models\ProgramEnrollment;
use App\Models\QuranCompletion;
use App\Models\Student;
use App\Services\QuranJourneyService;
use App\Support\QuranProgramSettings;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuranProgramController extends Controller
{
    public function __construct(
        private readonly QuranJourneyService $journeys,
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
                'pendingCompletions' => $pendingCompletions->count(),
                'untestedMonth' => $untestedIds->count(),
            ],
            'pendingCompletions' => $pendingCompletions,
            'qualifyingStudents' => $activeQualifying->map(fn ($e) => $e->student)->filter(),
            'ijazahStudents' => $activeIjazah->map(fn ($e) => $e->student)->filter(),
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
            'exams' => $exams,
            'monthLabel' => fn (string $m) => QuranProgramSettings::monthLabel($m),
        ]);
    }

    /** @return Collection<int, Student> */
    private function studentsByLatestResult(string $result)
    {
        return Student::query()
            ->whereHas('quranRecitationSessions', function ($q) use ($result) {
                $q->where('result', $result);
            })
            ->with('classroom:id,name')
            ->get()
            ->filter(function (Student $student) use ($result) {
                $latest = $student->quranRecitationSessions()->orderByDesc('date')->orderByDesc('created_at')->first();

                return $latest && $latest->result?->value === $result;
            })
            ->take(8);
    }

    private function closeToCompletionStudents()
    {
        return Student::query()
            ->whereDoesntHave('quranCompletions', fn ($q) => $q->where('status', QuranCompletionStatus::Confirmed))
            ->whereHas('quranRecitationSessions', fn ($q) => $q->where('type', 'new'))
            ->get()
            ->filter(function (Student $student) {
                return $student->quranRecitationSessions()->where('type', 'new')->sum('amount')
                    >= QuranProgramSettings::CLOSE_TO_COMPLETION_PAGES;
            })
            ->values()
            ->take(8);
    }
}
