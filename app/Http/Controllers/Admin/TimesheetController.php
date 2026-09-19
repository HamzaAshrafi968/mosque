<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\WorkSlot;
use App\Services\WorkSlotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * عمليات فترات العمل الفعلية: التسجيل (من/إلى أو عدد ساعات)، التعديل،
 * الحذف، التوليد من الجدول الأسبوعي، وفحص التداخل — أما العرض فأصبح
 * داخل مركز الدفعات والرواتب (admin.payroll.*).
 */
class TimesheetController extends Controller
{
    public function __construct(
        private readonly WorkSlotService $slots,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'required_without:hours', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_without:hours', 'date_format:H:i'],
            'hours' => ['nullable', 'required_without:start_time', 'numeric', 'min:0.25', 'max:'.$this->slots->maxSlotHours()],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $teacher = Teacher::query()->findOrFail($data['teacher_id']);

        try {
            $this->slots->create($teacher, $data, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'تمت إضافة فترة العمل');
    }

    public function update(Request $request, WorkSlot $workSlot): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'required_without:hours', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_without:hours', 'date_format:H:i'],
            'hours' => ['nullable', 'required_without:start_time', 'numeric', 'min:0.25', 'max:'.$this->slots->maxSlotHours()],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->slots->update($workSlot, $data, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'تم تحديث فترة العمل');
    }

    public function destroy(Request $request, WorkSlot $workSlot): RedirectResponse
    {
        try {
            $this->slots->delete($workSlot, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'تم حذف فترة العمل');
    }

    public function generate(Request $request, Teacher $teacher): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        try {
            $created = $this->slots->generateFromSchedule($teacher, $data['date'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with(
            'success',
            $created > 0
                ? "تم توليد {$created} فترة من الجدول الأسبوعي"
                : 'لا فترات جديدة — لا جدول لهذا اليوم أو الفترات مسجلة مسبقاً'
        );
    }

    /** فترات يوم محدد (JSON) لفحص التداخل الفوري في الواجهة. */
    public function daySlots(Request $request): JsonResponse
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $slots = WorkSlot::query()
            ->forTeacher($data['teacher_id'])
            ->onDate($data['date'])
            ->orderBy('start_time')
            ->get(['id', 'start_time', 'end_time']);

        return response()->json([
            'slots' => $slots->map(fn (WorkSlot $slot) => [
                'id' => $slot->id,
                'start' => substr($slot->start_time, 0, 5),
                'end' => substr($slot->end_time, 0, 5),
            ])->values(),
        ]);
    }
}
