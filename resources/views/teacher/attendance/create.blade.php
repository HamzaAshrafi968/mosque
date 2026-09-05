@extends('layouts.app')

@section('title', 'تسجيل الحضور')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <h2 class="text-lg font-bold text-gray-800 p-4 border-b">تسجيل حضور جلسة</h2>
    <div class="p-4">
        <form method="GET" action="{{ route('teacher.attendance.create') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الشعبة <span class="text-red-500">*</span></label>
                <select name="section_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">اختر شعبة من شعبك...</option>
                    @foreach($sections as $sec)
                        <option value="{{ $sec->id }}" @selected($section?->id === $sec->id)>
                            {{ $sec->classroom?->name }} - {{ $sec->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">التاريخ</label>
                <input type="date" name="date" value="{{ $date }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="flex items-end">
                <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">عرض الطلاب</button>
            </div>
        </form>
    </div>
</div>

@if($section && $students->isNotEmpty())
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <h2 class="text-lg font-bold text-gray-800 p-4 border-b flex items-center justify-between">
            <span>تسجيل الحضور — {{ $section->classroom?->name }} / {{ $section->name }}</span>
            <span class="text-sm font-normal text-gray-500">{{ now()->parse($date)->translatedFormat('l Y-m-d') }}</span>
        </h2>
        <x-attendance-marks-form
            :students="$students"
            :existing="$existing"
            date="{{ $date }}"
            action="{{ route('teacher.attendance.store') }}"
            submit-label="حفظ الحضور" />
    </div>
@elseif($section)
    <div class="bg-white rounded-xl shadow p-6 text-center text-gray-500">لا يوجد طلاب نشطون في هذه الشعبة</div>
@endif
@endsection
