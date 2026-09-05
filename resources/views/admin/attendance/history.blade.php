@extends('layouts.app')

@section('title', 'جدول الحضور التفصيلي')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden mb-6 p-4">
    <form method="GET" action="{{ route('admin.attendance.history') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">الشعبة</label>
            <select name="section_id" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                @foreach($sections as $sec)
                    <option value="{{ $sec->id }}" @selected($section?->id === $sec->id)>{{ $sec->classroom?->name }} - {{ $sec->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">من</label>
            <input type="date" name="from" value="{{ $from }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">إلى</label>
            <input type="date" name="to" value="{{ $to }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2">
        </div>
        <div>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">عرض</button>
        </div>
    </form>
</div>

@if($section)
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-bold text-gray-800">جدول حضور {{ $section->name }} <span class="text-sm font-normal text-gray-500">من {{ $from }} إلى {{ $to }}</span></h2>
        <a href="{{ route('admin.attendance.create', ['section_id' => $section->id]) }}"
           class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تسجيل جلسة جديدة</a>
    </div>
    <x-attendance-history-grid :sessions="$sessions" :rows="$rows" />
@else
    <div class="bg-white rounded-xl shadow p-6 text-center text-gray-500">لا توجد شعب</div>
@endif
@endsection
