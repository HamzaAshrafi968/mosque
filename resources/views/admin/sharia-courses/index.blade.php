@extends('layouts.app')

@section('title', 'الدورات الشرعية')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">📚 الدورات الشرعية</h2>
            <p class="text-sm text-gray-500 mt-1">دروس ومحاضرات وطلاب مستقلون + حضور وغياب</p>
        </div>
        <a href="{{ route('admin.sharia-courses.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ دورة جديدة</a>
    </div>

    <form method="GET" action="{{ route('admin.sharia-courses.index') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div class="md:col-span-2">
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
                        <th class="px-4 py-3 text-right">المشرف</th>
                        <th class="px-4 py-3 text-right">التواريخ</th>
                        <th class="px-4 py-3 text-right">الطلاب</th>
                        <th class="px-4 py-3 text-right">الدروس</th>
                        <th class="px-4 py-3 text-right">الحالة</th>
                        <th class="px-4 py-3 text-center">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($courses as $course)
                    <tr class="border-t">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('admin.sharia-courses.show', $course) }}" class="font-bold text-gray-800 hover:text-emerald-700">{{ $course->name }}</a>
                            <div class="flex items-center gap-2 mt-1">
                                @if($course->isFromSuperAdmin())
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-violet-100 text-violet-800">من مدير الجوامع</span>
                                @endif
                                @if($course->location)<span class="text-xs text-gray-400">{{ $course->location }}</span>@endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @forelse($course->supervisors as $supervisor)
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 mb-0.5">{{ $supervisor->name }}</span>
                            @empty
                                <span class="text-gray-300">—</span>
                            @endforelse
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-xs text-gray-500">
                            {{ $course->start_date?->format('Y-m-d') ?? '—' }} ← {{ $course->end_date?->format('Y-m-d') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">{{ $course->students_count }}</td>
                        <td class="px-4 py-3">{{ $course->lessons_count }}</td>
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
                            <div class="flex items-center justify-center gap-3">
                                <a href="{{ route('admin.sharia-courses.show', $course) }}" class="text-xs text-emerald-700 hover:underline">عرض</a>
                                <a href="{{ route('admin.sharia-courses.edit', $course) }}" class="text-xs text-blue-600 hover:underline">تعديل</a>
                                <form method="POST" action="{{ route('admin.sharia-courses.destroy', $course) }}" onsubmit="return confirm('حذف الدورة سيحذف دروسها وطلابها وسجلات حضورهم. متأكد؟')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-600 hover:underline">حذف</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">لا توجد دورات شرعية بعد</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">{{ $courses->links() }}</div>
    </div>
</div>
@endsection
