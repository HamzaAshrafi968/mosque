@extends('layouts.app')

@section('title', 'البرنامج التأهيلي - تقييماتي')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">📋 البرنامج التأهيلي — تقييماتي الأسبوعية</h2>
            <p class="text-sm text-gray-500 mt-1">كل أسبوع يسجل تقييم مستقل — التقييمات السابقة لا تُستبدل أبداً</p>
        </div>
        <a href="{{ route('teacher.quran.qualifying.evaluations.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ تقييم أسبوعي</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <h3 class="font-bold text-gray-800 mb-3">طلابي في البرنامج التأهيلي ({{ $students->count() }})</h3>
        @if($students->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach($students as $student)
                    <a href="{{ route('teacher.quran.qualifying.evaluations.create', ['student_id' => $student->id]) }}"
                       class="inline-flex items-center gap-2 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 text-sm font-bold px-3 py-1.5 rounded-full transition">
                        {{ $student->name }}
                        <span class="text-emerald-500">+</span>
                    </a>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-400">لا يوجد طلاب تأهيليون ضمن نطاقك حالياً.</p>
        @endif
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right">الطالب</th>
                        <th class="px-4 py-3 text-right">الأسبوع (من - إلى)</th>
                        <th class="px-4 py-3 text-right">المقدار</th>
                        <th class="px-4 py-3 text-right">المقروء</th>
                        <th class="px-4 py-3 text-right">النتيجة</th>
                        <th class="px-4 py-3 text-right">ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($evaluations as $evaluation)
                    <tr class="border-t">
                        <td class="px-4 py-3 font-bold text-gray-800">
                            <a href="{{ route('teacher.quran.students.journey', $evaluation->student) }}" class="hover:text-emerald-700">{{ $evaluation->student->name }}</a>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $evaluation->week_start->format('Y-m-d') }} ← {{ $evaluation->week_end->format('Y-m-d') }}</td>
                        <td class="px-4 py-3">{{ $evaluation->amount }}</td>
                        <td class="px-4 py-3">{{ $evaluation->recited_portion ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'px-2 py-0.5 rounded-full text-xs font-bold',
                                'bg-green-100 text-green-800' => $evaluation->result->value === 'passed',
                                'bg-yellow-100 text-yellow-800' => $evaluation->result->value === 'needs_review',
                                'bg-red-100 text-red-800' => $evaluation->result->value === 'failed',
                            ])>{{ $evaluation->result->label() }}</span>
                        </td>
                        <td class="px-4 py-3 max-w-xs truncate">{{ $evaluation->notes ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">لم تسجل أي تقييم أسبوعي بعد</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">{{ $evaluations->links() }}</div>
    </div>
</div>
@endsection
