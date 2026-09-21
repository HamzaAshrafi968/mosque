<?php

namespace App\Http\Controllers\Student;

use App\Enums\ProgramType;
use App\Enums\QuranListeningProgramStatus;
use App\Enums\QuranReading;
use App\Services\AuthorizationService;
use App\Services\QuranListeningProgramService;
use App\Services\QuranProgramBatchService;
use App\Services\QuranProgramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * «برامجي» في بوابة الطالب: برامج الاستماع (الإجازة/التأهيلي/القراءات) —
 * عرض فقط للتسميع والاختبار (يسجّلهما الأستاذ/المدير)، مع تسجيل ذاتي
 * اختياري في برنامج القراءات بعد إتمام الإجازة (المرحلة المتقدمة).
 */
class QuranListeningProgramController extends BaseStudentController
{
    public function __construct(
        private readonly QuranListeningProgramService $programs,
        private readonly QuranProgramBatchService $batches,
        private readonly QuranProgramService $programEnrollments,
        private readonly AuthorizationService $authorization,
    ) {}

    public function index(Request $request): View
    {
        $student = $this->currentStudent($request);
        $programs = $this->programs->programsForStudent($student);

        $selected = $request->filled('program_id')
            ? $programs->firstWhere('id', $request->input('program_id'))
            : null;

        $selected ??= $programs->firstWhere('status', QuranListeningProgramStatus::Active)
            ?? $programs->first();

        $panel = $selected
            ? $this->batches->panelData($selected)
            : QuranListeningProgramService::emptyPanel();

        return view('student.quran-programs', array_merge($panel, [
            'student' => $student,
            'programs' => $programs,
            'selectedProgram' => $selected,
            'types' => ProgramType::cases(),
            'readings' => QuranReading::cases(),
            'hasCompletedIjazah' => $this->programEnrollments->hasCompletedIjazah($student),
            'canSelfEnroll' => $this->authorization->can($request->user(), 'quran_training.enroll'),
            'enrolledReadings' => $programs
                ->where('type', ProgramType::Readings)
                ->where('status', QuranListeningProgramStatus::Active)
                ->pluck('reading'),
            'canTest' => false,
            'canCancel' => false,
            'actions' => [
                'index' => route('student.quran-programs.index'),
                'tasmee' => null,
                'test' => null,
                'placement' => null,
                'cancel' => null,
                'show' => null,
            ],
        ]));
    }

    /** التسجيل الذاتي الاختياري في برنامج القراءات — بعد إتمام الإجازة فقط. */
    public function enroll(Request $request): RedirectResponse
    {
        $student = $this->currentStudent($request);

        $data = $request->validate([
            'reading' => ['required', Rule::enum(QuranReading::class)],
        ]);

        $this->programEnrollments->assertReadingsEligible($student, 'reading');

        $program = $this->programs->enrollReadings(
            $student,
            QuranReading::from($data['reading']),
            $request->user(),
        );

        return redirect()
            ->route('student.quran-programs.index', ['program_id' => $program->id])
            ->with('success', 'تم تسجيلك في '.$program->displayLabel().' — بالتوفيق!');
    }
}
