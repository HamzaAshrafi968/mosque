<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\Teacher\Payroll\BuildMyPayrollPageData;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * مسار «كشوفي» التاريخي — يُعرض الآن ضمن صفحة «رواتبي» الموحّدة (نفس العرض)،
 * فيبقى الرابط صالحاً دون صفحة منفصلة.
 */
class TimesheetController extends BaseTeacherController
{
    public function __construct(private readonly BuildMyPayrollPageData $pageData) {}

    public function index(Request $request): View
    {
        $teacher = $this->currentTeacher($request);

        $input = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? null;
        $month = $input
            ? CarbonImmutable::createFromFormat('Y-m', $input)->startOfMonth()
            : CarbonImmutable::now()->startOfMonth();

        return view('teacher.payroll.index', $this->pageData->execute($teacher, $month));
    }
}
