@extends('layouts.app')

@section('title', 'إدخال الدرجات')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="p-4">
        <h2 class="text-lg font-bold text-gray-800">{{ $exam->title }}</h2>
        <div class="text-gray-600 text-sm mt-1">
            <span>{{ $exam->subject?->name }}</span>
            <span class="mx-2">|</span>
            <span>{{ $exam->classroom?->name }}</span>
            <span class="mx-2">|</span>
            <span>الدرجة الكلية: {{ $exam->total_marks }}</span>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <form method="POST" action="{{ route('teacher.grades.store', $exam) }}">
        @csrf
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-sm">
                    <th class="px-4 py-3 text-right">الطالب</th>
                    <th class="px-4 py-3 text-right">الدرجة</th>
                    <th class="px-4 py-3 text-right">الحالة الحالية</th>
                </tr>
            </thead>
            <tbody>
                @forelse($students as $student)
                    @php
                        $existingGrade = $grades->get($student->id);
                    @endphp
                    <tr>
                        <td class="px-4 py-3 border-t">{{ $student->name }}</td>
                        <td class="px-4 py-3 border-t">
                            <input type="number" name="scores[{{ $student->id }}]"
                                   min="0" max="{{ $exam->total_marks }}" step="0.5"
                                   value="{{ old('scores.'.$student->id, $existingGrade?->score) }}"
                                   class="w-28 border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        </td>
                        <td class="px-4 py-3 border-t text-sm">
                            @if($existingGrade)
                                @if($existingGrade->status === 'draft')
                                    <span class="text-gray-600">مسودة</span>
                                @elseif($existingGrade->status === 'submitted')
                                    <span class="text-blue-600">مرسلة</span>
                                @elseif($existingGrade->status === 'approved')
                                    <span class="text-green-600">معتمدة</span>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-6 text-center text-gray-500">لا يوجد طلاب في هذا الصف</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4 border-t flex gap-3">
            <button type="submit" name="action" value="save" class="bg-gray-500 hover:bg-gray-600 text-white font-bold px-4 py-2 rounded-lg">حفظ كمسودة</button>
            <button type="submit" name="action" value="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">إرسال للاعتماد</button>
        </div>
    </form>
</div>
@endsection
