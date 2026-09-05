@extends('layouts.app')

@section('title', 'درجاتي')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">درجاتي</h1>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-right">
                    <th class="px-4 py-3 font-medium">الامتحان</th>
                    <th class="px-4 py-3 font-medium">المادة</th>
                    <th class="px-4 py-3 font-medium">التاريخ</th>
                    <th class="px-4 py-3 font-medium">الدرجة</th>
                    <th class="px-4 py-3 font-medium">النسبة</th>
                    <th class="px-4 py-3 font-medium">النتيجة</th>
                </tr>
            </thead>
            <tbody>
                @forelse($grades as $row)
                    @php $grade = $row['grade']; $exam = $grade->exam; $passed = (float) $grade->score >= (int) $exam->pass_marks; @endphp
                    <tr class="border-t border-gray-100">
                        <td class="px-4 py-3 font-bold text-gray-800">{{ $exam->title }}</td>
                        <td class="px-4 py-3">{{ $exam->subject?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $exam->exam_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 font-bold">{{ $grade->score }} / {{ $exam->total_marks }}</td>
                        <td class="px-4 py-3">{{ $row['percentage'] }}٪</td>
                        <td class="px-4 py-3">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-bold {{ $passed ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                                {{ $passed ? 'ناجح' : 'راسب' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">لم تُنشر أي درجات بعد</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
