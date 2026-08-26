@extends('layouts.app')

@section('title', 'كشف الدرجات')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden mb-6 p-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <span class="text-sm text-gray-500">الاختبار:</span>
            <span class="font-bold mr-2">{{ $exam->title }}</span>
        </div>
        <div>
            <span class="text-sm text-gray-500">المادة:</span>
            <span class="font-bold mr-2">{{ $exam->subject?->name }}</span>
        </div>
        <div>
            <span class="text-sm text-gray-500">الصف:</span>
            <span class="font-bold mr-2">{{ $exam->classroom?->name }}</span>
        </div>
        <div>
            <span class="text-sm text-gray-500">الدرجة الكلية:</span>
            <span class="font-bold mr-2">{{ $exam->total_marks }}</span>
        </div>
        <div>
            <span class="text-sm text-gray-500">علامة النجاح:</span>
            <span class="font-bold mr-2 text-emerald-700">{{ $exam->pass_marks }}</span>
        </div>
    </div>
</div>

<div class="flex flex-wrap gap-3 mb-4">
    @php
        $submitted = $grades->whereIn('status', ['submitted', 'approved']);
        $passed = $submitted->where(fn ($g) => (float) $g->score >= (float) $exam->pass_marks)->count();
        $failed = $submitted->count() - $passed;
        $rate = $submitted->count() > 0 ? round(($passed / $submitted->count()) * 100, 1) : null;
    @endphp
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-2 text-sm font-bold">✅ ناجح: {{ $passed }}</div>
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-2 text-sm font-bold">❌ راسب: {{ $failed }}</div>
    <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-xl px-4 py-2 text-sm font-bold">📊 نسبة النجاح: {{ $rate !== null ? $rate . '%' : '—' }}</div>
</div>

<div class="flex flex-wrap gap-3 mb-4">
    <form method="POST" action="{{ route('admin.grades.approve', $exam) }}">
        @csrf
        @method('PATCH')
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">اعتماد النتائج</button>
    </form>
    <button onclick="window.print()" class="bg-gray-700 hover:bg-gray-800 text-white font-bold px-4 py-2 rounded-lg">طباعة كشف الدرجات</button>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    @if($grades->isEmpty())
        <div class="px-4 py-6 text-center text-gray-500">لا توجد درجات</div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-sm">
                        <th class="px-4 py-3 text-right whitespace-nowrap">#</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الطالب</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الدرجة</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">النسبة المئوية</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">النتيجة</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($grades as $index => $grade)
                        <tr>
                            <td class="px-4 py-3 border-t text-gray-500 whitespace-nowrap">{{ $index + 1 }}</td>
                            <td class="px-4 py-3 border-t font-bold whitespace-nowrap">{{ $grade->student?->name }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $grade->score }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ round(($grade->score / max($exam->total_marks, 1)) * 100) }}%</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">
                                @if(in_array($grade->status, ['submitted', 'approved']))
                                    @if((float) $grade->score >= (float) $exam->pass_marks)
                                        <span class="px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">ناجح</span>
                                    @else
                                        <span class="px-2 py-1 rounded-full text-xs font-bold bg-red-100 text-red-800">راسب</span>
                                    @endif
                                @else
                                    <span class="text-gray-400 text-xs">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">
                                @if($grade->status === 'draft')
                                    <span class="text-gray-600">مسودة</span>
                                @elseif($grade->status === 'submitted')
                                    <span class="text-yellow-600">بانتظار الاعتماد</span>
                                @elseif($grade->status === 'approved')
                                    <span class="text-green-600">معتمدة</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
