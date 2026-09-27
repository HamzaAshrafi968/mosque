@extends('layouts.app')

@section('title', 'الرحلة القرآنية - '.$student->name)

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
@endphp
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('teacher.quran.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← القرآن والبرامج</a>
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">{{ $student->name }} — الرحلة القرآنية</h2>
        </div>
        <div class="flex gap-2">
            @if ($can('quran.tasmee.create'))
                <a href="{{ route('teacher.quran.tasmee.create', ['student_id' => $student->id]) }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ تسميع</a>
            @endif
        </div>
    </div>

    <div class="bg-gradient-to-l from-emerald-700 to-teal-700 text-white rounded-2xl p-6 shadow-lg">
        <div class="flex flex-wrap items-center gap-6">
            <div>
                <div class="text-xs text-emerald-200">المرحلة الحالية</div>
                <div class="text-xl font-extrabold">{{ $journey['stage_label'] }}</div>
            </div>
            @if($journey['completion'])
                <div class="text-sm"><div class="text-emerald-200 text-xs">إتمام الحفظ</div><div class="font-bold">{{ $journey['completion']['completed_at'] ?? '—' }}</div></div>
            @endif
            <div class="text-sm"><div class="text-emerald-200 text-xs">إجمالي جديد</div><div class="font-bold">{{ $journey['stats']['new_amount'] }}</div></div>
            <div class="text-sm"><div class="text-emerald-200 text-xs">إجمالي مراجعة</div><div class="font-bold">{{ $journey['stats']['revision_amount'] }}</div></div>
            @if($journey['stats']['latest_tasmee'])
                <div class="text-sm"><div class="text-emerald-200 text-xs">آخر نتيجة</div><div class="font-bold">{{ $journey['stats']['latest_tasmee']->result?->label() ?? '—' }}</div></div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">🗺️ مسار الرحلة</div>
            <div class="p-5">
                @foreach($journey['timeline'] as $item)
                    <div class="flex gap-3">
                        <span @class([
                            'w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0 mt-0.5',
                            'bg-emerald-600 text-white' => $item['done'],
                            'bg-gray-200 text-gray-400' => !$item['done'],
                        ])>
                            @if($item['done']) ✓ @else ● @endif
                        </span>
                        <div class="pb-3">
                            <div @class(['text-sm font-bold', 'text-gray-800' => $item['done'], 'text-gray-400' => !$item['done']])>
                                {{ $item['label'] }}
                                @if($item['optional'] ?? false)
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-violet-100 text-violet-700 align-middle">اختياري</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-400">{{ $item['date'] !== '—' ? $item['date'] : '—' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="space-y-6">
            @if($journey['qualifying'])
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-3 border-b bg-gray-50 flex justify-between items-center">
                        <span class="font-bold text-gray-800">📋 تقييمات البرنامج التأهيلي</span>
                        @if ($can('qualifying.create'))
                            <a href="{{ route('teacher.quran.qualifying.evaluations.create', ['student_id' => $student->id]) }}" class="text-xs bg-emerald-700 hover:bg-emerald-800 text-white px-3 py-1.5 rounded-lg font-bold">+ تقييم</a>
                        @endif
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="bg-gray-50 text-gray-600 text-xs">
                                <th class="px-4 py-2 text-right">الأسبوع</th><th class="px-4 py-2 text-right">المقدار</th><th class="px-4 py-2 text-right">النتيجة</th><th class="px-4 py-2 text-right">المقيّم</th>
                            </tr></thead>
                            <tbody>
                            @forelse($weeklyEvaluations as $evaluation)
                                <tr class="border-t">
                                    <td class="px-4 py-2">{{ $evaluation->week_start->format('Y-m-d') }}</td>
                                    <td class="px-4 py-2">{{ $evaluation->amount }}</td>
                                    <td class="px-4 py-2">
                                        <span @class([
                                            'px-2 py-0.5 rounded-full text-xs font-bold',
                                            'bg-green-100 text-green-800' => $evaluation->result->value === 'passed',
                                            'bg-yellow-100 text-yellow-800' => $evaluation->result->value === 'needs_review',
                                            'bg-red-100 text-red-800' => $evaluation->result->value === 'failed',
                                        ])>{{ $evaluation->result->label() }}</span>
                                    </td>
                                    <td class="px-4 py-2">{{ $evaluation->evaluatedBy?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">لا توجد تقييمات بعد</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            @if($journey['ijazah'])
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-3 border-b bg-gray-50 flex justify-between items-center">
                        <span class="font-bold text-gray-800">📜 تقييمات برنامج الإجازة</span>
                        @if ($can('ijazah.create'))
                            <a href="{{ route('teacher.quran.ijazah.evaluations.create', ['student_id' => $student->id]) }}" class="text-xs bg-emerald-700 hover:bg-emerald-800 text-white px-3 py-1.5 rounded-lg font-bold">+ تقييم</a>
                        @endif
                    </div>
                    <div class="px-5 py-3 border-b bg-emerald-50/40 flex flex-wrap items-center gap-2">
                        <span class="text-xs font-bold text-gray-600">أسابيع {{ $monthLabel($currentMonth) }}:</span>
                        @for($week = 1; $week <= 4; $week++)
                            @php $weekEvaluation = $currentMonthWeeklyEvaluations->get($week); @endphp
                            <span @class([
                                'text-[11px] font-bold rounded-full px-2.5 py-1',
                                'bg-green-100 text-green-800' => $weekEvaluation?->result?->value === 'passed',
                                'bg-yellow-100 text-yellow-800' => $weekEvaluation?->result?->value === 'needs_review',
                                'bg-red-100 text-red-800' => $weekEvaluation?->result?->value === 'failed',
                                'bg-gray-100 text-gray-500' => ! $weekEvaluation,
                            ])>الأسبوع {{ $week }}: {{ $weekEvaluation?->result?->label() ?? 'لم يُقيَّم' }}</span>
                        @endfor
                        @if ($can('ijazah.view'))
                            <a href="{{ route('teacher.quran.ijazah.month', [$student, $currentMonth]) }}" class="text-xs text-emerald-700 hover:underline ms-auto">إدارة الأسابيع ←</a>
                        @endif
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead><tr class="bg-gray-50 text-gray-600 text-xs">
                                <th class="px-4 py-2 text-right">الشهر</th><th class="px-4 py-2 text-right">المقدار</th><th class="px-4 py-2 text-right">النتيجة</th><th class="px-4 py-2 text-right">المقيّم</th>
                            </tr></thead>
                            <tbody>
                            @forelse($monthlyEvaluations as $evaluation)
                                <tr class="border-t">
                                    <td class="px-4 py-2">{{ $monthLabel($evaluation->month) }}</td>
                                    <td class="px-4 py-2">{{ $evaluation->amount }}</td>
                                    <td class="px-4 py-2">
                                        <span @class([
                                            'px-2 py-0.5 rounded-full text-xs font-bold',
                                            'bg-green-100 text-green-800' => $evaluation->result->value === 'passed',
                                            'bg-yellow-100 text-yellow-800' => $evaluation->result->value === 'needs_review',
                                            'bg-red-100 text-red-800' => $evaluation->result->value === 'failed',
                                        ])>{{ $evaluation->result->label() }}</span>
                                    </td>
                                    <td class="px-4 py-2">{{ $evaluation->evaluatedBy?->name ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">لا توجد تقييمات بعد</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- المرحلة المتقدمة الاختيارية: القراءات العشر (بعد إتمام الإجازة) --}}
            <div class="bg-white rounded-2xl shadow-sm border border-violet-200 overflow-hidden">
                <div class="px-5 py-3 border-b bg-violet-50 flex justify-between items-center">
                    <span class="font-bold text-violet-900">📖 برنامج القراءات — مرحلة متقدمة اختيارية</span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-violet-100 text-violet-700">اختياري</span>
                </div>

                @if($journey['readings']->isNotEmpty())
                    <div class="divide-y divide-gray-100">
                        @foreach($journey['readings'] as $row)
                            <div class="px-5 py-4 space-y-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="font-bold text-gray-800">{{ $row['reading_label'] }}</div>
                                    <div class="flex items-center gap-2">
                                        <span @class([
                                            'px-2 py-0.5 rounded-full text-xs font-bold',
                                            'bg-emerald-100 text-emerald-800' => $row['status'] === \App\Enums\ProgramEnrollmentStatus::Active,
                                            'bg-sky-100 text-sky-800' => $row['status'] === \App\Enums\ProgramEnrollmentStatus::Completed,
                                        ])>{{ $row['status']->label() }}</span>
                                        @if($row['program_id'] && $can('quran_training.view'))
                                            <a href="{{ route('teacher.quran.programs.index', ['type' => 'readings', 'program_id' => $row['program_id']]) }}" class="text-xs font-bold text-emerald-700 hover:underline">عرض الدورة ←</a>
                                        @endif
                                    </div>
                                </div>
                                @if($row['progress'])
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                                            <div class="bg-violet-600 h-2" style="width: {{ $row['progress']['percentage'] }}%"></div>
                                        </div>
                                        <span class="text-[11px] font-bold text-gray-500">{{ $row['progress']['passed_juz'] }}/{{ $row['progress']['total_juz'] }} جزء</span>
                                    </div>
                                    <div class="text-[11px] text-gray-400">
                                        الدفعات: {{ $row['progress']['passed_batches'] }}/{{ $row['progress']['total_batches'] }}
                                        @if($row['progress']['current_batch'])
                                            · الدفعة الحالية {{ $row['progress']['current_batch'] }} ({{ $row['progress']['current_status']?->label() }})
                                        @endif
                                    </div>
                                @endif
                                <div class="text-[11px] text-gray-400">
                                    بدأ {{ $row['started_at'] ?? '—' }}@if($row['completed_at']) · أُتم {{ $row['completed_at'] }}@endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="px-5 py-4 bg-gray-50/60 @if($journey['readings']->isNotEmpty()) border-t @endif">
                    @if($journey['readings_eligible'])
                        @if($canEnrollReadings)
                            <form method="POST" action="{{ route('teacher.quran.programs.enroll') }}" class="flex flex-wrap items-end gap-3">
                                @csrf
                                <input type="hidden" name="student_id" value="{{ $student->id }}">
                                <input type="hidden" name="redirect_to" value="journey">
                                <div class="min-w-56 flex-1">
                                    <label class="block text-xs font-bold text-gray-600 mb-1">تسجيل في قراءة جديدة (يمكن أكثر من قراءة)</label>
                                    <select name="reading" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                        @foreach($readings as $reading)
                                            <option value="{{ $reading->value }}" @selected(old('reading') === $reading->value)>
                                                {{ $reading->label() }}@if(in_array($reading->value, $enrolledReadings, true)) — مسجّلة مسبقاً @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('reading')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                    @error('student_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                                </div>
                                <button class="bg-violet-700 hover:bg-violet-800 text-white font-bold px-6 py-2 rounded-xl text-sm">تسجيل في القراءات</button>
                            </form>
                        @else
                            <p class="text-xs text-gray-400">التسجيل في القراءات متاح لمن يملك صلاحية تعديل برامج القرآن.</p>
                        @endif
                    @else
                        <p class="text-xs text-gray-500">🔒 يُفتح التسجيل الاختياري في القراءات (القراءات العشر) بعد إتمام برنامج الإجازة — المرحلة اختيارية ولا تمنع إتمام الرحلة.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($exams->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">✅ الاختبارات الشهرية</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="bg-gray-50 text-gray-600 text-xs">
                        <th class="px-4 py-2 text-right">الشهر</th><th class="px-4 py-2 text-right">الحالة</th><th class="px-4 py-2 text-right">الدرجة</th><th class="px-4 py-2 text-right">المشرف</th>
                    </tr></thead>
                    <tbody>
                    @foreach($exams as $exam)
                        <tr class="border-t">
                            <td class="px-4 py-2">{{ $monthLabel($exam->month) }}</td>
                            <td class="px-4 py-2">
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-bold',
                                    'bg-gray-100 text-gray-600' => $exam->exam_status->value === 'not_tested',
                                    'bg-sky-100 text-sky-800' => $exam->exam_status->value === 'tested',
                                    'bg-green-100 text-green-800' => $exam->exam_status->value === 'passed',
                                    'bg-red-100 text-red-800' => $exam->exam_status->value === 'failed',
                                ])>{{ $exam->exam_status->label() }}</span>
                            </td>
                            <td class="px-4 py-2">{{ $exam->grade ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $exam->supervisor?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b bg-gray-50 flex justify-between items-center">
            <span class="font-bold text-gray-800">🗣️ سجل التسميع الأخير</span>
            @if ($can('quran_batch.view'))
                <a href="{{ route('teacher.quran.batches.index', ['student_id' => $student->id]) }}" class="text-xs text-emerald-700 hover:underline">كل السجل</a>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50 text-gray-600 text-xs">
                    <th class="px-4 py-2 text-right">التاريخ</th><th class="px-4 py-2 text-right">النوع</th><th class="px-4 py-2 text-right">المقدار</th><th class="px-4 py-2 text-right">المقروء</th><th class="px-4 py-2 text-right">النتيجة</th><th class="px-4 py-2 text-right">المعلم</th>
                </tr></thead>
                <tbody>
                @forelse($tasmee as $session)
                    <tr class="border-t">
                        <td class="px-4 py-2">{{ $session->date->format('Y-m-d') }}</td>
                        <td class="px-4 py-2">
                            <span @class([
                                'px-2 py-0.5 rounded-full text-xs font-bold',
                                'bg-emerald-100 text-emerald-800' => $session->type->value === 'new',
                                'bg-sky-100 text-sky-800' => $session->type->value === 'revision',
                            ])>{{ $session->type->label() }}</span>
                        </td>
                        <td class="px-4 py-2">{{ $session->amount }}</td>
                        <td class="px-4 py-2">{{ $session->recited_portion ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $session->result?->label() ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $session->teacher?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">لم يسجل له أي تسميع بعد</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
