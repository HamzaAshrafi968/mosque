@extends('layouts.app')

@section('title', 'الرحلة القرآنية - '.$student->name)

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.quran.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← البرامج القرآنية</a>
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">{{ $student->name }} — الرحلة القرآنية</h2>
            <p class="text-sm text-gray-500 mt-1">الصف: {{ $student->classroom?->name ?? '—' }} @if($student->section) · شعبة {{ $student->section->name }} @endif</p>
        </div>
        <div class="flex gap-2 flex-wrap">
            <a href="{{ route('admin.quran.tasmee.create', ['student_id' => $student->id]) }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ تسميع</a>
            @if(!$journey['is_hafiz'])
                <a href="{{ route('admin.quran.completions.create') }}" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">تسجيل إتمام الحفظ</a>
            @endif
            @if($journey['is_hafiz'])
                <a href="{{ route('admin.quran.hafiz.profile', $student) }}" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">📿 ملف الحافظ</a>
            @endif
        </div>
    </div>

    <div class="bg-gradient-to-l from-emerald-700 to-teal-700 text-white rounded-2xl p-6 shadow-lg">
        <div class="flex flex-wrap items-center gap-6">
            <div class="flex items-center gap-3">
                <span class="text-4xl">🕌</span>
                <div>
                    <div class="text-xs text-emerald-200">المرحلة الحالية</div>
                    <div class="text-xl font-extrabold">{{ $journey['stage_label'] }}</div>
                </div>
            </div>
            @if($journey['completion'])
                <div class="text-sm">
                    <div class="text-emerald-200 text-xs">تاريخ إتمام الحفظ</div>
                    <div class="font-bold">{{ $journey['completion']['completed_at'] ?? '—' }}</div>
                </div>
            @endif
            <div class="text-sm">
                <div class="text-emerald-200 text-xs">إجمالي التسميع الجديد</div>
                <div class="font-bold">{{ $journey['stats']['new_amount'] }}</div>
            </div>
            <div class="text-sm">
                <div class="text-emerald-200 text-xs">إجمالي المراجعة</div>
                <div class="font-bold">{{ $journey['stats']['revision_amount'] }}</div>
            </div>
            @if($journey['stats']['latest_tasmee'])
                <div class="text-sm">
                    <div class="text-emerald-200 text-xs">آخر نتيجة تسميع</div>
                    <div class="font-bold">{{ $journey['stats']['latest_tasmee']->result?->label() ?? '—' }}</div>
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-1 bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">🗺️ مسار الرحلة</div>
            <div class="p-5 space-y-0">
                @foreach($journey['timeline'] as $item)
                    <div class="flex gap-3">
                        <div class="flex flex-col items-center">
                            <span @class([
                                'w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0',
                                'bg-emerald-600 text-white' => $item['done'],
                                'bg-gray-200 text-gray-400' => !$item['done'],
                            ])>
                                @if($item['done']) ✓ @else ● @endif
                            </span>
                            @if(!$loop->last) <span class="w-px flex-1 bg-gray-200 my-1"></span> @endif
                        </div>
                        <div class="pb-4">
                            <div @class(['text-sm font-bold', 'text-gray-800' => $item['done'], 'text-gray-400' => !$item['done']])>{{ $item['label'] }}</div>
                            <div class="text-xs text-gray-400">{{ $item['date'] !== '—' ? \Carbon\Carbon::parse($item['date'])->format('Y-m-d') : '—' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="lg:col-span-2 space-y-6">
            @if($journey['qualifying'])
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b bg-gray-50 flex justify-between items-center">
                    <span class="font-bold text-gray-800">📋 البرنامج التأهيلي (أسبوعي)</span>
                    <a href="{{ route('admin.quran.qualifying.evaluations.create', ['student_id' => $student->id]) }}" class="text-xs bg-emerald-700 hover:bg-emerald-800 text-white px-3 py-1.5 rounded-lg font-bold">+ تقييم أسبوع</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="bg-gray-50 text-gray-600 text-xs">
                            <th class="px-4 py-2 text-right">الأسبوع</th>
                            <th class="px-4 py-2 text-right">المقدار</th>
                            <th class="px-4 py-2 text-right">المقروء</th>
                            <th class="px-4 py-2 text-right">النتيجة</th>
                            <th class="px-4 py-2 text-right">المقيّم</th>
                        </tr></thead>
                        <tbody>
                        @forelse($weeklyEvaluations as $evaluation)
                            <tr class="border-t">
                                <td class="px-4 py-2">{{ $evaluation->week_start->format('Y-m-d') }} ← {{ $evaluation->week_end->format('Y-m-d') }}</td>
                                <td class="px-4 py-2">{{ $evaluation->amount }}</td>
                                <td class="px-4 py-2">{{ $evaluation->recited_portion ?? '—' }}</td>
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
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">لا توجد تقييمات بعد — كل أسبوع يسجل تقييماً مستقلاً</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            @if($journey['ijazah'])
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b bg-gray-50 flex justify-between items-center">
                    <span class="font-bold text-gray-800">📜 برنامج الإجازة (شهري)</span>
                    <a href="{{ route('admin.quran.ijazah.evaluations.create', ['student_id' => $student->id]) }}" class="text-xs bg-emerald-700 hover:bg-emerald-800 text-white px-3 py-1.5 rounded-lg font-bold">+ تقييم شهر</a>
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
                    <a href="{{ route('admin.quran.ijazah.month', [$student, $currentMonth]) }}" class="text-xs text-emerald-700 hover:underline ms-auto">إدارة الأسابيع ←</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="bg-gray-50 text-gray-600 text-xs">
                            <th class="px-4 py-2 text-right">الشهر</th>
                            <th class="px-4 py-2 text-right">المقدار</th>
                            <th class="px-4 py-2 text-right">المقروء</th>
                            <th class="px-4 py-2 text-right">النتيجة</th>
                            <th class="px-4 py-2 text-right">المقيّم</th>
                        </tr></thead>
                        <tbody>
                        @forelse($monthlyEvaluations as $evaluation)
                            <tr class="border-t">
                                <td class="px-4 py-2">{{ $monthLabel($evaluation->month) }}</td>
                                <td class="px-4 py-2">{{ $evaluation->amount }}</td>
                                <td class="px-4 py-2">{{ $evaluation->recited_portion ?? '—' }}</td>
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
                            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">لا توجد تقييمات بعد</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            @if($exams->isNotEmpty())
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">✅ الاختبارات الشهرية للحافظ</div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="bg-gray-50 text-gray-600 text-xs">
                            <th class="px-4 py-2 text-right">الشهر</th>
                            <th class="px-4 py-2 text-right">الحالة</th>
                            <th class="px-4 py-2 text-right">الدرجة</th>
                            <th class="px-4 py-2 text-right">المشرف</th>
                            <th class="px-4 py-2 text-right">إعادة مطلوبة</th>
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
                                <td class="px-4 py-2">
                                    @foreach($exam->revisions as $revision)
                                        <span class="inline-block text-[11px] px-2 py-0.5 rounded-full bg-yellow-50 border border-yellow-200 text-yellow-800 ml-1">
                                            {{ $revision->juz ? 'جزء '.$revision->juz : '' }}
                                            {{ $revision->juz && $revision->amount ? '·' : '' }}
                                            {{ $revision->amount ? 'مقدار '.$revision->amount : '' }}
                                            ({{ $revision->status->label() }})
                                        </span>
                                    @endforeach
                                    @if($exam->revisions->isEmpty()) <span class="text-gray-300">—</span> @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-5 py-3 border-b bg-gray-50 flex justify-between items-center">
                    <span class="font-bold text-gray-800">🗣️ سجل التسميع</span>
                    <a href="{{ route('admin.quran.batches.index', ['student_id' => $student->id]) }}" class="text-xs text-emerald-700 hover:underline">كل السجل</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="bg-gray-50 text-gray-600 text-xs">
                            <th class="px-4 py-2 text-right">التاريخ</th>
                            <th class="px-4 py-2 text-right">النوع</th>
                            <th class="px-4 py-2 text-right">المقدار</th>
                            <th class="px-4 py-2 text-right">المقروء</th>
                            <th class="px-4 py-2 text-right">النتيجة</th>
                            <th class="px-4 py-2 text-right">المعلم</th>
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
    </div>
</div>
@endsection
