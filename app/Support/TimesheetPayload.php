<?php

namespace App\Support;

use App\Models\Teacher;
use App\Models\WorkSlot;

/**
 * تحويل كيانات ساعات العمل والرواتب إلى حمولات JSON موحّدة للـ API
 * (الويب والجوال) مع تسميات عربية جاهزة للعرض.
 */
final class TimesheetPayload
{
    public static function teacher(Teacher $teacher): array
    {
        return [
            'id' => $teacher->id,
            'name' => $teacher->name,
            'pay_type' => $teacher->pay_type?->value,
            'pay_type_label' => $teacher->pay_type?->label(),
            'study_sessions' => $teacher->relationLoaded('studySessions')
                ? $teacher->studySessions->pluck('name')->values()
                : [],
        ];
    }

    public static function slot(WorkSlot $slot): array
    {
        return [
            'id' => $slot->id,
            'teacher_id' => $slot->teacher_id,
            'date' => $slot->date->toDateString(),
            'start_time' => substr((string) $slot->start_time, 0, 5),
            'end_time' => substr((string) $slot->end_time, 0, 5),
            'duration_minutes' => (int) $slot->duration_minutes,
            'duration_label' => WorkSlot::formatMinutes((int) $slot->duration_minutes),
            'notes' => $slot->notes,
        ];
    }

    /** @param  array<string, mixed>  $summary  مخرج PayrollPeriodService::summary/summaries */
    public static function summary(array $summary): array
    {
        return [
            'period_id' => $summary['period']?->id,
            'pay_type' => $summary['pay_type']->value,
            'pay_type_label' => $summary['pay_type']->label(),
            'total_minutes' => $summary['total_minutes'],
            'total_label' => WorkSlot::formatMinutes($summary['total_minutes']),
            'planned_minutes' => $summary['planned_minutes'],
            'planned_label' => WorkSlot::formatMinutes($summary['planned_minutes']),
            'hourly_rate' => $summary['hourly_rate'],
            'monthly_salary' => $summary['monthly_salary'],
            'gross' => $summary['gross'],
            'paid' => $summary['paid'],
            'remaining' => $summary['remaining'],
            'missing_rates' => $summary['missing_rates'],
            'breakdown' => $summary['breakdown'],
            'status' => $summary['status']->value,
            'status_label' => $summary['status']->label(),
            'payment_state' => $summary['state']->value,
            'payment_state_label' => $summary['state']->label(),
        ];
    }
}
