@extends('layouts.app')

@section('title', 'أسابيع الإجازة — ' . $student->name)

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.quran.ijazah.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← برنامج الإجازة</a>
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">📜 {{ $student->name }} — {{ $monthLabel($month) }}</h2>
            <p class="text-sm text-gray-500 mt-1">أربعة تقييمات أسبوعية داخل الشهر + ملخص شهري</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.quran.ijazah.month', [$student, $previousMonth]) }}" class="bg-white border border-gray-300 text-gray-700 text-sm font-bold px-3 py-2 rounded-lg">الشهر السابق ←</a>
            <a href="{{ route('admin.quran.ijazah.month', [$student, $nextMonth]) }}" class="bg-white border border-gray-300 text-gray-700 text-sm font-bold px-3 py-2 rounded-lg">→ الشهر التالي</a>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="font-black text-pine-950">الملخص الشهري</h3>
                <p class="text-xs text-gray-500 mt-0.5">قاعدة الإكمال تعتمد على التقييم الشهري — الأسابيع تفصيلية</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-[11px] font-black text-emerald-700 bg-emerald-50 rounded-full px-3 py-1.5">{{ $passedWeeks }} / 4 أسابيع ناجحة</span>
                @if($monthly)
                    <span @class([
                        'px-2.5 py-1 rounded-full text-xs font-bold',
                        'bg-green-100 text-green-800' => $monthly->result->value === 'passed',
                        'bg-yellow-100 text-yellow-800' => $monthly->result->value === 'needs_review',
                        'bg-red-100 text-red-800' => $monthly->result->value === 'failed',
                    ])>{{ $monthly->result->label() }}</span>
                @else
                    <a href="{{ route('admin.quran.ijazah.evaluations.create', ['student_id' => $student->id, 'month' => $month]) }}"
                       class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-3 py-2 rounded-lg">تسجيل التقييم الشهري</a>
                @endif
            </div>
        </div>

        <div class="mt-4 h-2 rounded-full bg-gray-100 overflow-hidden">
            <div class="h-full rounded-full bg-gradient-to-l from-emerald-400 to-emerald-700" style="width: {{ min(100, ($passedWeeks / 4) * 100) }}%"></div>
        </div>

        @if($monthly)
            <div class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div><span class="text-gray-500">المقدار:</span> <span class="font-bold">{{ $monthly->amount }}</span></div>
                <div><span class="text-gray-500">المقروء:</span> {{ $monthly->recited_portion ?? '—' }}</div>
                <div><span class="text-gray-500">المشرف:</span> {{ $monthly->evaluatedBy?->name ?? '—' }}</div>
                <div><span class="text-gray-500">ملاحظات:</span> {{ $monthly->notes ?? '—' }}</div>
            </div>
        @else
            <p class="text-sm text-gray-400 mt-3">لا يوجد تقييم شهري مسجل لهذا الشهر بعد.</p>
        @endif
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @for($week = 1; $week <= 4; $week++)
            @php
                $evaluation = $evaluations->get($week);
                [$weekStart, $weekEnd] = $weekDefaults[$week];
            @endphp
            <x-ijazah-week-card
                :week="$week"
                :evaluation="$evaluation"
                :month="$month"
                :student="$student"
                :action="$evaluation ? route('admin.quran.ijazah.weekly.update', $evaluation) : route('admin.quran.ijazah.weekly.store')"
                :method="$evaluation ? 'PATCH' : 'POST'"
                :results="$results"
                :week-start="$weekStart"
                :week-end="$weekEnd"
                :teachers="$teachers"
                :evaluated-by="$evaluation?->evaluated_by"
                :can-delete="true"
                :delete-action="$evaluation ? route('admin.quran.ijazah.weekly.destroy', $evaluation) : null" />
        @endfor
    </div>
</div>
@endsection
