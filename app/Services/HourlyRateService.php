<?php

namespace App\Services;

use App\Models\HourlyRate;
use App\Models\Teacher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * سجل أسعار الساعة: كل أستاذ له سعر ساري من تاريخ إلى تاريخ، والراتب كله
 * بالساعات (لا راتب شهري). إضافة/تغيير السعر يعيد احتساب الكشوف المفتوحة
 * فوراً، والكشوف المغلقة تحتفظ بلقطتها.
 *
 * إضافة سعر جديد تُغلق تلقائياً أي سعر مفتوح يتعارض معه (يُقصر قبل بدايته،
 * أو يُنقل بعد نهايته، أو يُحذف إن غطّاه السعر الجديد كاملاً) فلا يحتاج
 * المدير إلى إغلاق السابق يدوياً. التعارض مع سعر مغلق يبقى مرفوضاً.
 */
class HourlyRateService
{
    public function __construct(
        private readonly HourlyRateResolver $resolver,
        private readonly PayrollPeriodService $payroll,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array{rate: float|string, effective_from: string, effective_to?: ?string}  $data
     */
    public function create(Teacher $teacher, array $data, ?User $actor = null): HourlyRate
    {
        $from = $data['effective_from'];
        $to = $data['effective_to'] ?? null;

        $rate = DB::transaction(function () use ($teacher, $data, $from, $to, $actor) {
            $this->reconcileOpenRates($teacher, $from, $to, $actor);

            $this->resolver->assertNoOverlap($teacher, $from, $to);

            $rate = HourlyRate::create([
                'tenant_id' => $teacher->tenant_id,
                'teacher_id' => $teacher->id,
                'rate' => $data['rate'],
                'effective_from' => $from,
                'effective_to' => $to,
                'created_by' => $actor?->id,
            ]);

            $this->audit->logModel('hourly_rate.created', $rate, actor: $actor);

            return $rate;
        });

        $this->payroll->refreshOpenForTeacher($teacher);

        return $rate;
    }

    /**
     * تغيير سعر الساعة ابتداءً من تاريخ: يُغلق السعر المفتوح السابق قبل
     * التاريخ الجديد ثم يُنشئ السعر الجديد — أسهل طريقة للمدير من كشف الأستاذ.
     */
    public function change(Teacher $teacher, float|string $rate, string $from, ?User $actor = null): HourlyRate
    {
        return $this->create($teacher, [
            'rate' => $rate,
            'effective_from' => $from,
        ], $actor);
    }

    /**
     * يزيل تعارض الأسعار المفتوحة مع النطاق الجديد قبل حفظه:
     * - سعر يبدأ قبل النطاق: يُقصر على ما قبل بدايته.
     * - سعر يبدأ داخل النطاق أو بعده والنطاق مفتوح: يُحذف (غطّاه الجديد كاملاً).
     * - سعر يبدأ داخل النطاق والنطاق منتهٍ: يُنقل ليبدأ بعد نهاية النطاق.
     */
    private function reconcileOpenRates(Teacher $teacher, string $from, ?string $to, ?User $actor): void
    {
        $newStart = CarbonImmutable::parse($from);
        $newEnd = $to !== null ? CarbonImmutable::parse($to) : null;

        $openRates = HourlyRate::query()
            ->where('teacher_id', $teacher->id)
            ->whereNull('effective_to')
            ->orderBy('effective_from')
            ->get();

        foreach ($openRates as $open) {
            $openStart = CarbonImmutable::parse($open->effective_from);

            // النطاق الجديد ينتهي قبل بداية السعر المفتوح → لا تعارض.
            if ($newEnd !== null && $newEnd->lt($openStart)) {
                continue;
            }

            $before = $open->getAttributes();

            if ($openStart->lt($newStart)) {
                $open->update(['effective_to' => $newStart->subDay()->toDateString()]);
                $this->audit->logModel('hourly_rate.updated', $open, $before, actor: $actor);

                continue;
            }

            if ($newEnd === null) {
                $this->audit->logModel('hourly_rate.deleted', $open, actor: $actor);
                $open->delete();

                continue;
            }

            $open->update(['effective_from' => $newEnd->addDay()->toDateString()]);
            $this->audit->logModel('hourly_rate.updated', $open, $before, actor: $actor);
        }
    }
}
