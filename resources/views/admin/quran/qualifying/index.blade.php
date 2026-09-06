@extends('layouts.app')

@section('title', 'البرنامج التأهيلي')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">📋 البرنامج التأهيلي (أسبوعي)</h2>
            <p class="text-sm text-gray-500 mt-1">يلتحق الحافظ تلقائياً بعد تأكيد إتمام الحفظ — كل أسبوع له تقييم مستقل لا يُستبدل</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.quran.qualifying.evaluations.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ تقييم أسبوعي</a>
            <a href="{{ route('admin.quran.hafiz.index') }}" class="bg-white border border-gray-300 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">الحفاظ</a>
        </div>
    </div>

    <div class="flex gap-2">
        @foreach(['active' => '🟢 الملتحقون حالياً', 'completed' => '✔️ المكملون'] as $value => $label)
            <a href="{{ route('admin.quran.qualifying.index', ['status' => $value]) }}"
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
                        <th class="px-4 py-3 text-right">الأسابيع الناجحة</th>
                        <th class="px-4 py-3 text-right">آخر نتيجة</th>
                        @if($status === 'active')<th class="px-4 py-3 text-center">إجراء</th>@endif
                    </tr>
                </thead>
                <tbody>
                @forelse($enrollments as $enrollment)
                    @php
                        $evals = $enrollment->student->qualifyingWeeklyEvaluations()->orderByDesc('week_start')->get();
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
                        <td class="px-4 py-3">
                            @if($last)
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-bold',
                                    'bg-green-100 text-green-800' => $last->result->value === 'passed',
                                    'bg-yellow-100 text-yellow-800' => $last->result->value === 'needs_review',
                                    'bg-red-100 text-red-800' => $last->result->value === 'failed',
                                ])>{{ $last->result->label() }} — أسبوع {{ $last->week_start->format('Y-m-d') }}</span>
                            @else <span class="text-gray-300">لم يسجل بعد</span> @endif
                        </td>
                        @if($status === 'active')
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <div class="flex gap-2 justify-center">
                                <a href="{{ route('admin.quran.qualifying.evaluations.create', ['student_id' => $enrollment->student->id]) }}" class="text-xs text-emerald-700 hover:underline">+ تقييم</a>
                                @if($passed >= \App\Support\QuranProgramSettings::QUALIFYING_MIN_PASSED_WEEKS)
                                    <form method="POST" action="{{ route('admin.quran.qualifying.enrollments.complete', $enrollment) }}"
                                          onsubmit="return confirm('سيُنهى البرنامج التأهيلي وينتقل الطالب تلقائياً لبرنامج الإجازة. متأكد؟')">
                                        @csrf
                                        <button class="text-xs text-amber-700 hover:underline">إنهاء البرنامج</button>
                                    </form>
                                @else
                                    <span class="text-[11px] text-gray-400" title="الحد الأدنى: {{ \App\Support\QuranProgramSettings::QUALIFYING_MIN_PASSED_WEEKS }} أسابيع ناجحة">
                                        الحد الأدنى: {{ \App\Support\QuranProgramSettings::QUALIFYING_MIN_PASSED_WEEKS }} أسبوع ناجح
                                    </span>
                                @endif
                            </div>
                        </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">
                        @if($status === 'active') لا يوجد طلاب في البرنامج حالياً — يلتحقون تلقائياً بعد تأكيد إتمام الحفظ @else لا يوجد مكملون بعد @endif
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">{{ $enrollments->links() }}</div>
    </div>
</div>
@endsection
