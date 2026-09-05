@extends('layouts.app')

@section('title', 'إدارة الصفوف والشعب')

@section('content')
<div class="bg-white rounded-xl shadow overflow-hidden p-4 mb-6">
    <div class="flex items-center justify-between mb-3">
        <h2 class="font-bold text-gray-800">الصفوف</h2>
        <a href="{{ route('admin.classrooms.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">إضافة صف</a>
    </div>
</div>

@forelse($classrooms as $classroom)
    <div class="bg-white rounded-xl shadow overflow-hidden mb-4">
        <div class="px-4 py-3 bg-gray-50 flex justify-between items-center">
            <a href="{{ route('admin.classrooms.show', $classroom) }}" class="font-bold text-lg text-emerald-800 hover:underline">
                {{ $classroom->name }}
            </a>
            <div class="flex items-center gap-4 text-sm text-gray-500">
                <span>{{ $classroom->students_count }} طالب نشط</span>
                <span>{{ $classroom->sections_count ?? $classroom->sections->count() }} شعبة</span>
                @if($classroom->status !== 'active')
                    <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">مؤرشف</span>
                @endif
            </div>
        </div>
        <div class="p-4">
            @if($classroom->sections->where('status', 'active')->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach($classroom->sections->where('status', 'active') as $section)
                        <a href="{{ route('admin.sections.show', $section) }}"
                           class="inline-flex items-center gap-2 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-900 rounded-lg px-4 py-2 text-sm transition">
                            <span class="font-bold">{{ $section->name }}</span>
                            <span class="text-xs text-emerald-600">لوحة الشعبة ←</span>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="text-sm text-gray-500 mb-4">لا توجد شعب نشطة</div>
            @endif
        </div>
    </div>
@empty
    <div class="bg-white rounded-xl shadow p-6 text-center text-gray-500">لا توجد صفوف — أضف صفاً جديداً للبدء</div>
@endforelse
@endsection
