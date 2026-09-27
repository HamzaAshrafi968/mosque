<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\QuranMemorizationBatchStatus;
use App\Models\Student;
use App\Services\QuranBatchPanelService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * جدول «دفعات الحفظ»: كل الطلاب المسجّلين (لا فقط من لديهم صفوف دفعات)
 * مع ملخص الدفعة الحالية لكل طالب، وترشيح بالحالة الحالية.
 */
trait ListsQuranBatchStudents
{
    /**
     * @param  Builder<Student>  $query
     * @return array{0: LengthAwarePaginator<int, Student>, 1: array<string, array<string, mixed>>}
     */
    protected function paginateBatchStudents(Builder $query, QuranBatchPanelService $panel, ?string $status): array
    {
        if ($status === null || $status === '') {
            $students = $query->paginate(20)->withQueryString();

            return [$students, $panel->summariesFor($students->getCollection())];
        }

        $matching = $query->get();
        $summaries = $panel->summariesFor($matching);

        $filtered = $matching
            ->filter(function (Student $student) use ($summaries, $status) {
                $summary = $summaries[$student->id] ?? null;

                if ($summary === null) {
                    return false;
                }

                return $status === 'completed'
                    ? $summary['completed']
                    : $summary['status']->value === $status;
            })
            ->values();

        $page = Paginator::resolveCurrentPage();

        return [
            new LengthAwarePaginator(
                $filtered->forPage($page, 20)->values(),
                $filtered->count(),
                20,
                $page,
                ['path' => Paginator::resolveCurrentPath(), 'query' => request()->query()],
            ),
            $summaries,
        ];
    }

    /**
     * حالات الدفعة الحالية القابلة للترشيح (المقفلة/الناجحة لا تظهر كحالة حالية).
     *
     * @return array<int, QuranMemorizationBatchStatus>
     */
    protected function batchStatusOptions(): array
    {
        return collect(QuranMemorizationBatchStatus::cases())
            ->reject(fn (QuranMemorizationBatchStatus $status) => in_array(
                $status,
                [QuranMemorizationBatchStatus::Locked, QuranMemorizationBatchStatus::Passed],
                true,
            ))
            ->values()
            ->all();
    }
}
