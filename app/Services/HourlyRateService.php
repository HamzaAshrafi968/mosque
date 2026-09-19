<?php

namespace App\Services;

use App\Enums\PayType;
use App\Models\HourlyRate;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * إنشاء سعر ساعة لأستاذ: فحص التداخل، ثم تحويل نوع الأجر إلى «بالساعة»
 * تلقائياً (سعر بلا نوع «بالساعة» لا يغيّر الاحتساب)، وأخيراً إعادة
 * احتساب الكشوف المفتوحة حتى يظهر الراتب الجديد فوراً.
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
        $this->resolver->assertNoOverlap($teacher, $data['effective_from'], $data['effective_to'] ?? null);

        $rate = DB::transaction(function () use ($teacher, $data, $actor) {
            $rate = HourlyRate::create([
                'tenant_id' => $teacher->tenant_id,
                'teacher_id' => $teacher->id,
                'rate' => $data['rate'],
                'effective_from' => $data['effective_from'],
                'effective_to' => $data['effective_to'] ?? null,
                'created_by' => $actor?->id,
            ]);

            $this->audit->logModel('hourly_rate.created', $rate, actor: $actor);

            $this->switchToHourly($teacher, $actor);

            return $rate;
        });

        $this->payroll->refreshOpenForTeacher($teacher);

        return $rate;
    }

    /** تحويل الأستاذ إلى الأجر بالساعة إن لم يكن كذلك (مع تدقيق). */
    public function switchToHourly(Teacher $teacher, ?User $actor = null): bool
    {
        if (($teacher->pay_type ?? PayType::Monthly) === PayType::Hourly) {
            return false;
        }

        $before = $teacher->getAttributes();
        $teacher->update(['pay_type' => PayType::Hourly]);
        $this->audit->logModel('teacher.salary_updated', $teacher, $before, actor: $actor);

        return true;
    }
}
