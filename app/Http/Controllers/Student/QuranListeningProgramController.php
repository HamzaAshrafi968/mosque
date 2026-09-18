<?php

namespace App\Http\Controllers\Student;

use App\Enums\ProgramType;
use App\Enums\QuranListeningProgramStatus;
use App\Services\QuranListeningProgramService;
use App\Services\QuranProgramBatchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * «برامجي» في بوابة الطالب: برامج الاستماع (تدريبي/إجازة/تأهيلي) —
 * عرض فقط: التقدم، سجل التسميع، ونتائج الاختبارات. التسميع والاختبار
 * يسجّلهما الأستاذ/المدير.
 */
class QuranListeningProgramController extends BaseStudentController
{
    public function __construct(
        private readonly QuranListeningProgramService $programs,
        private readonly QuranProgramBatchService $batches,
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
            ? ($this->batches->supports($selected)
                ? $this->batches->panelData($selected)
                : $this->programs->panelData($selected))
            : QuranListeningProgramService::emptyPanel();

        return view('student.quran-programs', array_merge($panel, [
            'student' => $student,
            'programs' => $programs,
            'selectedProgram' => $selected,
            'types' => ProgramType::cases(),
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
}
