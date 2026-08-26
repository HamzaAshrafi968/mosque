@extends('layouts.app')

@section('title', 'تصحيح الواجب')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="p-4">
        <h2 class="text-lg font-bold text-gray-800">{{ $homework->title }}</h2>
        <div class="text-gray-600 text-sm mt-1">
            <span>{{ $homework->subject?->name }}</span>
            <span class="mx-2">|</span>
            <span>{{ $homework->classroom?->name }}</span>
            <span class="mx-2">|</span>
            <span>تاريخ التسليم: {{ $homework->due_date->format('Y-m-d') }}</span>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 text-gray-600 text-sm">
                    <th class="px-4 py-3 text-right whitespace-nowrap">الطالب</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الحالة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">الدرجة</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap">ملاحظات</th>
                    <th class="px-4 py-3 text-right whitespace-nowrap"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($submissions as $submission)
                    <tr>
                        <td class="px-4 py-3 border-t whitespace-nowrap">{{ $submission->student?->name }}</td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">
                            @if($submission->status === 'graded')
                                <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">مصحح</span>
                            @else
                                <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm">بانتظار التصحيح</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 border-t">
                            <form method="POST" action="{{ route('teacher.submissions.update', $submission) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="graded">
                                <input type="number" name="grade" value="{{ old('grade', $submission->grade) }}" step="0.5"
                                       class="w-24 border border-gray-300 rounded-lg px-2 py-1 focus:ring-2 focus:ring-emerald-500 focus:outline-none text-sm" placeholder="الدرجة">
                        </td>
                        <td class="px-4 py-3 border-t">
                                <input type="text" name="feedback" value="{{ $submission->feedback }}"
                                       class="w-full border border-gray-300 rounded-lg px-2 py-1 focus:ring-2 focus:ring-emerald-500 focus:outline-none text-sm" placeholder="ملاحظات">
                        </td>
                        <td class="px-4 py-3 border-t whitespace-nowrap">
                                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-3 py-1 rounded-lg text-sm">حفظ التصحيح</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500">لا توجد تسليمات</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
