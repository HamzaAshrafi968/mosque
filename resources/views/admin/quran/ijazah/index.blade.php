@extends('layouts.app')

@section('title', 'برنامج الإجازة')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">📜 برنامج الإجازة (شهري)</h2>
            <p class="text-sm text-gray-500 mt-1">يلتحق الطالب تلقائياً بعد إكمال البرنامج التأهيلي — كل شهر له تقييم تاريخي مستقل</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.quran.ijazah.evaluations.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ تقييم شهري</a>
        </div>
    </div>

    <div class="flex gap-2">
        @foreach(['active' => '🟢 الملتحقون حالياً', 'completed' => '🏁 أكملوا البرنامج'] as $value => $label)
            <a href="{{ route('admin.quran.ijazah.index', ['status' => $value]) }}"
               @class([
                   'px-4 py-2 rounded-lg text-sm font-bold border',
                   'bg-emerald-700 text-white border-emerald-700' => $status === $value,
                   'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' => $status !== $value,
               ])>{{ $label }}</a>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right">الطالب</th>
                        <th class="px-4 py-3 text-right">بداية البرنامج</th>
                        <th class="px-4 py-3 text-right">عدد التقييمات</th>
                        <th class="px-4 py-3 text-right">الأشهر الناجحة</th>
                        <th class="px-4 py-3 text-right">آخر شهر</th>
                        <th class="px-4 py-3 text-right">آخر نتيجة</th>
                        @if($status === 'active')<th class="px-4 py-3 text-center">إجراء</th>@endif
                    </tr>
                </thead>
                <tbody>
                @forelse($enrollments as $enrollment)
                    @php
                        $evals = $enrollment->student->ijazahMonthlyEvaluations()->orderByDesc('month')->get();
                        $last = $evals->first();
                        $passed = $evals->where('result', 'passed')->count();
                    @endphp
                    <tr class="border-t">
                        <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-800">
                            <a href="{{ route('admin.quran.journey', $enrollment->student) }}" class="hover:text-emerald-700">{{ $enrollment->student->name }}</a>
                            <div class="text-xs text-gray-400 font-normal">{{ $enrollment->student->classroom?->name }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $enrollment->started_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $evals->count() }}</td>
                        <td class="px-4 py-3">{{ $passed }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('admin.quran.ijazah.month', [$enrollment->student, $last?->month ?? now()->format('Y-m')]) }}" class="text-emerald-700 hover:underline">
                                {{ $last ? $monthLabel($last->month) : 'عرض الأسابيع' }}
                            </a>
                        </td>
                        <td class="px-4 py-3">
                            @if($last)
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-bold',
                                    'bg-green-100 text-green-800' => $last->result->value === 'passed',
                                    'bg-yellow-100 text-yellow-800' => $last->result->value === 'needs_review',
                                    'bg-red-100 text-red-800' => $last->result->value === 'failed',
                                ])>{{ $last->result->label() }}</span>
                            @else <span class="text-gray-300">لم يسجل بعد</span> @endif
                        </td>
                        @if($status === 'active')
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <div class="flex gap-2 justify-center">
                                <a href="{{ route('admin.quran.ijazah.month', [$enrollment->student, $last?->month ?? now()->format('Y-m')]) }}" class="text-xs text-pine-700 hover:underline">الأسابيع</a>
                                <a href="{{ route('admin.quran.ijazah.evaluations.create', ['student_id' => $enrollment->student->id]) }}" class="text-xs text-emerald-700 hover:underline">+ تقييم</a>
                                @if($passed >= \App\Support\QuranProgramSettings::IJAZAH_MIN_PASSED_MONTHS)
                                    <form method="POST" action="{{ route('admin.quran.ijazah.enrollments.complete', $enrollment) }}"
                                          onsubmit="return confirm('سيُنهى برنامج الإجازة (اكتمال الرحلة القرآنية). متأكد؟')">
                                        @csrf
                                        <button class="text-xs text-amber-700 hover:underline">إنهاء البرنامج</button>
                                    </form>
                                @else
                                    <span class="text-[11px] text-gray-400">يتطلب شهراً ناجحاً واحداً على الأقل</span>
                                @endif
                            </div>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">
                        @if($status === 'active') لا يوجد طلاب في برنامج الإجازة حالياً @else لا يوجد مكملون بعد @endif
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">{{ $enrollments->links() }}</div>
    </div>
</div>
@endsection
