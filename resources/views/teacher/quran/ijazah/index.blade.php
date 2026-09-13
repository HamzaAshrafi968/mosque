@extends('layouts.app')

@section('title', 'برنامج الإجازة - تقييماتي')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">📜 برنامج الإجازة — تقييماتي الشهرية</h2>
            <p class="text-sm text-gray-500 mt-1">كل شهر له تقييم تاريخي مستقل لا يُستبدل</p>
        </div>
        <a href="{{ route('teacher.quran.ijazah.evaluations.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ تقييم شهري</a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
        <h3 class="font-bold text-gray-800 mb-3">طلابي في برنامج الإجازة ({{ $students->count() }})</h3>
        @if($students->isNotEmpty())
            <div class="flex flex-wrap gap-2">
                @foreach($students as $student)
                    <span class="inline-flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-bold px-3 py-1.5 rounded-full">
                        <a href="{{ route('teacher.quran.ijazah.month', [$student, now()->format('Y-m')]) }}" class="hover:underline">{{ $student->name }}</a>
                        <a href="{{ route('teacher.quran.ijazah.evaluations.create', ['student_id' => $student->id]) }}" class="text-emerald-500 hover:text-emerald-700">+</a>
                    </span>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-400">لا يوجد طلاب إجازة ضمن نطاقك حالياً.</p>
        @endif
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right">الطالب</th>
                        <th class="px-4 py-3 text-right">الشهر</th>
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
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('teacher.quran.ijazah.month', [$evaluation->student, $evaluation->month]) }}" class="text-emerald-700 hover:underline">{{ $monthLabel($evaluation->month) }}</a>
                        </td>
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
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">لم تسجل أي تقييم شهري بعد</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">{{ $evaluations->links() }}</div>
    </div>
</div>
@endsection
