@extends('layouts.app')

@section('title', 'ملف الطالب')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="px-4 py-3 bg-emerald-700 text-white font-bold">معلومات الطالب</div>
    <div class="p-4 grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm text-gray-500">الاسم:</span>
            <span class="font-bold mr-2">{{ $student->name }}</span>
        </div>
        <div>
            <span class="text-sm text-gray-500">الجنس:</span>
            <span class="font-bold mr-2">{{ $student->gender === 'male' ? 'ذكر' : 'أنثى' }}</span>
        </div>
        <div>
            <span class="text-sm text-gray-500">تاريخ الميلاد:</span>
            <span class="font-bold mr-2">{{ $student->birth_date?->format('Y-m-d') ?? '—' }}</span>
        </div>
        <div>
            <span class="text-sm text-gray-500">الصف:</span>
            <span class="font-bold mr-2">{{ $student->classroom?->name ?? '—' }}</span>
        </div>
        <div>
            <span class="text-sm text-gray-500">الشعبة:</span>
            <span class="font-bold mr-2">{{ $student->section?->name ?? '—' }}</span>
        </div>
        <div>
            <span class="text-sm text-gray-500">الحالة:</span>
            <span @class([
                'px-2 py-1 rounded-full text-xs font-bold mr-2',
                'bg-green-100 text-green-800' => $student->status === 'active',
                'bg-gray-100 text-gray-800' => $student->status === 'archived',
            ])>{{ $student->status === 'active' ? 'نشط' : 'مؤرشف' }}</span>
        </div>
        <div>
            <span class="text-sm text-gray-500">ولي الأمر:</span>
            <span class="font-bold mr-2">{{ $student->guardian_name ?? '—' }}</span>
        </div>
        <div>
            <span class="text-sm text-gray-500">هاتف ولي الأمر:</span>
            <span class="font-bold mr-2">{{ $student->guardian_phone ?? '—' }}</span>
        </div>
        <div class="col-span-2">
            <span class="text-sm text-gray-500">ملاحظات:</span>
            <span class="font-bold mr-2">{{ $student->notes ?? '—' }}</span>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="px-4 py-3 bg-emerald-700 text-white font-bold">ملخص الحضور</div>
    <div class="p-4 grid grid-cols-3 gap-4 text-center">
        <div class="bg-green-50 rounded-lg p-3">
            <div class="text-2xl font-bold text-green-700">{{ $attendanceSummary['present'] ?? 0 }}</div>
            <div class="text-sm text-gray-600">حاضر</div>
        </div>
        <div class="bg-red-50 rounded-lg p-3">
            <div class="text-2xl font-bold text-red-700">{{ $attendanceSummary['absent'] ?? 0 }}</div>
            <div class="text-sm text-gray-600">غائب</div>
        </div>
        <div class="bg-yellow-50 rounded-lg p-3">
            <div class="text-2xl font-bold text-yellow-600">{{ $attendanceSummary['late'] ?? 0 }}</div>
            <div class="text-sm text-gray-600">متأخر</div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="px-4 py-3 bg-emerald-700 text-white font-bold">الدرجات</div>
    @if($student->grades->isEmpty())
        <div class="px-4 py-6 text-center text-gray-500">لا توجد درجات</div>
    @else
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-sm">
                    <th class="px-4 py-3 text-right">المادة</th>
                    <th class="px-4 py-3 text-right">الاختبار</th>
                    <th class="px-4 py-3 text-right">التاريخ</th>
                    <th class="px-4 py-3 text-right">الدرجة</th>
                    <th class="px-4 py-3 text-right">الحالة</th>
                </tr>
            </thead>
            <tbody>
                @foreach($student->grades as $grade)
                    <tr>
                        <td class="px-4 py-3 border-t">{{ $grade->exam->subject?->name }}</td>
                        <td class="px-4 py-3 border-t">{{ $grade->exam->title }}</td>
                        <td class="px-4 py-3 border-t">{{ $grade->exam->exam_date->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 border-t">{{ $grade->score }} / {{ $grade->exam->total_marks }}</td>
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
