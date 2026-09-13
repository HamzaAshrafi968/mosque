@extends('layouts.app')

@section('title', 'اختبارات الحفاظ — ' . $monthLabel($month))

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('teacher.quran.exams.index', ['year' => substr($month, 0, 4)]) }}" class="text-sm text-emerald-700 hover:text-emerald-800">← العودة لشبكة الأشهر</a>
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">✅ اختبارات الحفاظ — {{ $monthLabel($month) }}</h2>
            <p class="text-sm text-gray-500 mt-1">كل شهر له سجل مستقل؛ «لم يُختبر» لا تعني «راسب»</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('teacher.quran.exams.month', $previousMonth) }}" class="bg-white border border-gray-300 text-gray-700 text-sm font-bold px-3 py-2 rounded-lg">الشهر السابق ←</a>
            <form onsubmit="return false;">
                <input type="month" value="{{ $month }}" onchange="window.location.href='{{ url('teacher/quran/exams/month') }}/' + this.value"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm" aria-label="الشهر">
            </form>
            <a href="{{ route('teacher.quran.exams.month', $nextMonth) }}" class="bg-white border border-gray-300 text-gray-700 text-sm font-bold px-3 py-2 rounded-lg">→ الشهر التالي</a>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right">الحافظ</th>
                        <th class="px-4 py-3 text-right">الصف</th>
                        <th class="px-4 py-3 text-right">الحالة</th>
                        <th class="px-4 py-3 text-right">الدرجة</th>
                        <th class="px-4 py-3 text-right">تاريخ الاختبار</th>
                        <th class="px-4 py-3 text-right">إعادة مطلوبة</th>
                        <th class="px-4 py-3 text-center">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($exams as $exam)
                    <tr class="border-t">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('teacher.quran.students.journey', $exam->student) }}" class="font-bold text-gray-800 hover:text-emerald-700">{{ $exam->student->name }}</a>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $exam->student->classroom?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'px-2 py-0.5 rounded-full text-xs font-bold',
                                'bg-gray-100 text-gray-600' => $exam->exam_status->value === 'not_tested',
                                'bg-sky-100 text-sky-800' => $exam->exam_status->value === 'tested',
                                'bg-green-100 text-green-800' => $exam->exam_status->value === 'passed',
                                'bg-red-100 text-red-800' => $exam->exam_status->value === 'failed',
                            ])>{{ $exam->exam_status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $exam->grade !== null ? $exam->grade.' / 100' : '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $exam->exam_date?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if($exam->revisions->isNotEmpty())
                                @foreach($exam->revisions as $revision)
                                    <span class="inline-block text-[11px] px-2 py-0.5 rounded-full bg-yellow-50 border border-yellow-200 text-yellow-800 ml-1 mb-0.5">
                                        {{ $revision->juz ? 'جزء '.$revision->juz : '' }}
                                        {{ ($revision->juz && $revision->amount) ? '·' : '' }}
                                        {{ $revision->amount ? 'مقدار '.$revision->amount : '' }}
                                        ({{ $revision->status->label() }})
                                    </span>
                                @endforeach
                            @else <span class="text-gray-300">—</span> @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('teacher.quran.exams.show', $exam) }}" class="text-xs text-emerald-700 hover:underline font-bold">
                                {{ $exam->exam_status->value === 'not_tested' ? 'تسجيل نتيجة' : 'عرض / تعديل' }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">لا يوجد حفاظ مسجلون — أُكّد إتمام الحفظ أولاً</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
