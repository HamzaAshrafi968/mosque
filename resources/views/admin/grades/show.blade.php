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
    </div>
</div>

<div class="flex gap-3 mb-4">
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
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-sm">
                    <th class="px-4 py-3 text-right">#</th>
                    <th class="px-4 py-3 text-right">الطالب</th>
                    <th class="px-4 py-3 text-right">الدرجة</th>
                    <th class="px-4 py-3 text-right">النسبة المئوية</th>
                    <th class="px-4 py-3 text-right">الحالة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($grades as $index => $grade)
                    <tr>
                        <td class="px-4 py-3 border-t text-gray-500">{{ $index + 1 }}</td>
                        <td class="px-4 py-3 border-t font-bold">{{ $grade->student?->name }}</td>
                        <td class="px-4 py-3 border-t">{{ $grade->score }}</td>
                        <td class="px-4 py-3 border-t">{{ round(($grade->score / max($exam->total_marks, 1)) * 100) }}%</td>
                        <td class="px-4 py-3 border-t">
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
    @endif
</div>
@endsection
