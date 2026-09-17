@extends('layouts.app')

@section('title', 'الدورات الشرعية — مدير الجوامع')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">📚 الدورات الشرعية</h2>
            <p class="text-sm text-gray-500 mt-1">إنشاء دورة لجامع محدد مع مشرفين وطلاب، ويصل إشعار لمدير الجامع</p>
        </div>
        <a href="{{ route('super-admin.sharia-courses.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ دورة شرعية جديدة</a>
    </div>

    <form method="GET" action="{{ route('super-admin.sharia-courses.index') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">الجامع</label>
            <select name="mosque_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">كل الجوامع</option>
                @foreach($mosques as $mosque)
                    <option value="{{ $mosque->id }}" @selected($mosqueId === $mosque->id)>{{ $mosque->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">بحث بالاسم</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="اسم الدورة" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">الحالة</label>
            <select name="status" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">الكل</option>
                @foreach($statuses as $statusOption)
                    <option value="{{ $statusOption->value }}" @selected($status === $statusOption->value)>{{ $statusOption->label() }}</option>
                @endforeach
            </select>
        </div>
        <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تصفية</button>
    </form>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right">الدورة</th>
                        <th class="px-4 py-3 text-right">الجامع</th>
                        <th class="px-4 py-3 text-right">المشرفون</th>
                        <th class="px-4 py-3 text-right">الطلاب</th>
                        <th class="px-4 py-3 text-right">الحالة</th>
                        <th class="px-4 py-3 text-center">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($courses as $course)
                    <tr class="border-t">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="font-bold text-gray-800">{{ $course->name }}</div>
                            <div class="flex items-center gap-2 mt-1">
                                @if($course->isFromSuperAdmin())
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 text-violet-800">من مدير الجوامع</span>
                                @endif
                                @if($course->location)<span class="text-xs text-gray-400">{{ $course->location }}</span>@endif
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-700">{{ $course->tenant?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @forelse($course->supervisors as $supervisor)
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 mb-0.5">{{ $supervisor->name }}</span>
                            @empty
                                <span class="text-gray-300">—</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3">{{ $course->students_count }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'px-2 py-0.5 rounded-full text-xs font-bold',
                                'bg-gray-100 text-gray-600' => $course->status->value === 'draft',
                                'bg-emerald-100 text-emerald-800' => $course->status->value === 'active',
                                'bg-sky-100 text-sky-800' => $course->status->value === 'completed',
                                'bg-red-100 text-red-800' => $course->status->value === 'cancelled',
                            ])>{{ $course->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <form method="POST" action="{{ route('super-admin.mosques.enter', $course->tenant_id) }}">
                                @csrf
                                <button type="submit" class="text-xs text-emerald-700 hover:underline">الدخول للجامع</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">لا توجد دورات شرعية بعد</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">{{ $courses->links() }}</div>
    </div>
</div>
@endsection
