<?php

namespace App\Services;

use App\Models\HourlyRate;
use App\Models\Teacher;
use App\Models\WorkSlot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * حل سعر الساعة المطبق على كل فترة عمل حسب تاريخها (BR-09 / ADR-10):
 * كل فترة تُسعَّر بالسعر الساري في تاريخها، وغياب السعر يمنع الاحتساب
 * مع إظهار التواريخ الناقصة بدل استخدام صفر صامت.
 */
class HourlyRateResolver
{
    public function rateFor(Teacher $teacher, CarbonInterface|string $date): ?float
    {
        $rate = HourlyRate::query()
            ->where('teacher_id', $teacher->id)
            ->activeOn($date)
            ->orderByDesc('effective_from')
            ->first();

        return $rate !== null ? (float) $rate->rate : null;
    }

    /** الأسعار المتقاطعة مع نطاق تاريخي (للعرض والفحص). */
    public function ratesFor(Teacher $teacher, CarbonInterface|string $from, CarbonInterface|string $to): Collection
    {
        $from = $from instanceof CarbonInterface ? $from->toDateString() : $from;
        $to = $to instanceof CarbonInterface ? $to->toDateString() : $to;

        return HourlyRate::query()
            ->where('teacher_id', $teacher->id)
            ->whereDate('effective_from', '<=', $to)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
            ->orderBy('effective_from')
            ->get();
    }

    /**
     * تفصيل التسعير لمجموعة فترات: شريحة لكل سعر مع دقائقها ونطاق تواريخها.
     *
     * @param  Collection<int, WorkSlot>  $slots
     * @param  Collection<int, HourlyRate>|null  $preloadedRates  أسعار هذا الأستاذ المحمّلة مسبقاً (لمسارات الدفعة)
     * @return array{breakdown: array<int, array{rate: float, minutes: int, from: string, to: string}>, missing: array<int, string>}
     */
    public function breakdown(Teacher $teacher, Collection $slots, ?Collection $preloadedRates = null): array
    {
        $cache = [];
        $segments = [];
        $missing = [];

        foreach ($slots as $slot) {
            $date = CarbonImmutable::parse($slot->date)->toDateString();

            if (! array_key_exists($date, $cache)) {
                $cache[$date] = $preloadedRates !== null
                    ? $this->rateFromRows($preloadedRates, $date)
                    : $this->rateFor($teacher, $date);
            }

            $rate = $cache[$date];

            if ($rate === null) {
                $missing[$date] = $date;

                continue;
            }

            $key = number_format($rate, 2, '.', '');

            if (! isset($segments[$key])) {
                $segments[$key] = ['rate' => $rate, 'minutes' => 0, 'from' => $date, 'to' => $date];
            }

            $segments[$key]['minutes'] += (int) $slot->duration_minutes;

            if ($date < $segments[$key]['from']) {
                $segments[$key]['from'] = $date;
            }

            if ($date > $segments[$key]['to']) {
                $segments[$key]['to'] = $date;
            }
        }

        usort($segments, fn (array $a, array $b) => strcmp($a['from'], $b['from']));

        return ['breakdown' => array_values($segments), 'missing' => array_values($missing)];
    }

    /** السعر الساري في تاريخ من صفوف أسعار محمّلة مسبقاً (دون استعلام). */
    private function rateFromRows(Collection $rows, string $date): ?float
    {
        $match = $rows
            ->filter(fn (HourlyRate $rate) => $rate->effective_from->toDateString() <= $date
                && ($rate->effective_to === null || $rate->effective_to->toDateString() >= $date))
            ->sortByDesc(fn (HourlyRate $rate) => $rate->effective_from->toDateString())
            ->first();

        return $match !== null ? (float) $match->rate : null;
    }

    /** منع تداخل فترات السعر لنفس الأستاذ (شامل المفتوح). */
    public function assertNoOverlap(Teacher $teacher, string $from, ?string $to, ?string $ignoreId = null): void
    {
        $newEnd = $to ?: '9999-12-31';

        $conflicts = HourlyRate::query()
            ->where('teacher_id', $teacher->id)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->get()
            ->filter(function (HourlyRate $rate) use ($from, $newEnd) {
                $rateEnd = $rate->effective_to?->toDateString() ?? '9999-12-31';

                return $from <= $rateEnd && $rate->effective_from->toDateString() <= $newEnd;
            });

        if ($conflicts->isNotEmpty()) {
            $first = $conflicts->first();

            throw ValidationException::withMessages([
                'effective_from' => [
                    'يتعارض مع سعر مسجّل للفترة '.$first->effective_from->format('Y-m-d').' — '.($first->effective_to?->format('Y-m-d') ?? 'مفتوح')
                    .'. اختر تاريخاً بعد انتهائه أو احذف السعر المتعارض.',
                ],
            ]);
        }
    }
}
