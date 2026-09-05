@extends('layouts.app')

@section('title', 'الصف: '.$classroom->name)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.classrooms.index') }}" class="text-sm text-emerald-700 hover:underline">← الصفوف والشعب</a>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="px-4 py-4 bg-gradient-to-l from-emerald-800 to-emerald-700 text-white flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold">{{ $classroom->name }}</h1>
            <p class="text-sm text-emerald-100 mt-1">{{ $classroom->description ?: '—' }}</p>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <a href="{{ route('admin.classrooms.edit', $classroom) }}" class="bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg">تعديل الصف</a>
            <form method="POST" action="{{ route('admin.classrooms.destroy', $classroom) }}"
                  onsubmit="return confirm('حذف الصف؟ (يمنع إذا كانت شعبها بها طلاب نشطون)')">
                @csrf
                @method('DELETE')
                <button type="submit" class="bg-red-500/80 hover:bg-red-500 px-3 py-1.5 rounded-lg">حذف</button>
            </form>
        </div>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-x-reverse divide-gray-100 text-center">
        <div class="p-4">
            <div class="text-2xl font-bold text-gray-800">{{ $studentsCount }}</div>
            <div class="text-xs text-gray-500">طالب نشط</div>
        </div>
        <div class="p-4">
            <div class="text-2xl font-bold text-gray-800">{{ $sections->count() }}</div>
            <div class="text-xs text-gray-500">شعبة</div>
        </div>
        <div class="p-4">
            <div class="text-2xl font-bold text-gray-800">{{ $assignmentsCount }}</div>
            <div class="text-xs text-gray-500">تكليف معلمين نشط</div>
        </div>
        <div class="p-4">
            <div class="text-2xl font-bold text-amber-600">{{ $classroom->status === 'active' ? 'نشط' : 'مؤرشف' }}</div>
            <div class="text-xs text-gray-500">حالة الصف</div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow overflow-hidden p-4 mb-6">
    <h2 class="font-bold text-gray-800 mb-3">إضافة شعبة</h2>
    <form method="POST" action="{{ route('admin.sections.store', $classroom) }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
        @csrf
        <input type="text" name="name" required placeholder="اسم الشعبة (مثال: أ)"
               class="w-full border border-gray-300 rounded-lg px-3 py-2">
        <input type="text" name="description" placeholder="وصف اختياري"
               class="w-full border border-gray-300 rounded-lg px-3 py-2">
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg">إنشاء الشعبة</button>
    </form>
</div>

@if($sections->isNotEmpty())
    <h2 class="font-bold text-gray-800 mb-3">شعب الصف</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        @foreach($sections as $section)
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <div class="px-4 py-3 flex items-center justify-between border-b">
                    <div>
                        <div class="font-bold text-gray-800">{{ $section->name }}</div>
                        <div class="text-xs text-gray-500">{{ $section->description ?: '—' }}</div>
                    </div>
                    @if($section->status !== 'active')
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">مؤرشف</span>
                    @endif
                </div>
                <div class="p-4 grid grid-cols-3 gap-2 text-center text-sm">
                    <div><div class="font-bold text-emerald-700">{{ $section->students_count }}</div><div class="text-xs text-gray-500">طالب</div></div>
                    <div><div class="font-bold text-gray-700">{{ $section->teacher_assignments_count }}</div><div class="text-xs text-gray-500">معلم</div></div>
                    <div><div class="font-bold text-gray-700">{{ $section->attendance_sessions_count }}</div><div class="text-xs text-gray-500">جلسات حضور</div></div>
                </div>
                <div class="px-4 pb-4 flex gap-2 flex-wrap">
                    <a href="{{ route('admin.sections.show', $section) }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-3 py-1.5 rounded-lg">لوحة الشعبة</a>
                    <a href="{{ route('admin.attendance.history', ['section_id' => $section->id]) }}" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold px-3 py-1.5 rounded-lg">الحضور</a>
                    <form method="POST" action="{{ route('admin.sections.destroy', $section) }}" class="inline" onsubmit="return confirm('حذف الشعبة؟ (يمنع مع طلاب نشطون)')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline text-xs px-2 py-1.5">حذف</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
