@extends('layouts.app')

@section('title', 'اللقاءات الإيمانية')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">🕊️ اللقاءات الإيمانية</h2>
            <p class="text-sm text-gray-500 mt-1">لقاءات بإشراف المشايخ مع طلاب محددين + حضور + ملاحظات وإجراءات</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.faith-meetings.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ لقاء جديد</a>
            <a href="{{ route('admin.faith-meetings.templates') }}" class="bg-white border border-gray-300 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">القوالب</a>
        </div>
    </div>

    <div class="flex gap-2 flex-wrap">
        @foreach(['upcoming' => '📅 القادمة', 'past' => '🕐 المنتهية', 'cancelled' => '❌ الملغاة', 'all' => 'الكل'] as $value => $label)
            <a href="{{ route('admin.faith-meetings.index', ['filter' => $value]) }}"
               @class([
                   'px-4 py-2 rounded-lg text-sm font-bold border',
                   'bg-emerald-700 text-white border-emerald-700' => $filter === $value,
                   'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' => $filter !== $value,
               ])>{{ $label }}</a>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right">اللقاء</th>
                        <th class="px-4 py-3 text-right">التاريخ</th>
                        <th class="px-4 py-3 text-right">المشرف</th>
                        <th class="px-4 py-3 text-right">المعلم</th>
                        <th class="px-4 py-3 text-right">الطلاب</th>
                        <th class="px-4 py-3 text-right">الحضور</th>
                        <th class="px-4 py-3 text-right">الحالة</th>
                        <th class="px-4 py-3 text-center">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($meetings as $meeting)
                    <tr class="border-t">
                        <td class="px-4 py-3 font-bold text-gray-800">{{ $meeting->title }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ $meeting->date->format('Y-m-d') }}
                            @if($meeting->start_time)<div class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($meeting->start_time)->format('H:i') }}</div>@endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $meeting->supervisor?->name ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $meeting->teacher?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $meeting->student_attendances_count }}</td>
                        <td class="px-4 py-3">{{ $attendanceTaken($meeting) ? '✔️ تم' : '—' }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'px-2 py-0.5 rounded-full text-xs font-bold',
                                'bg-sky-100 text-sky-800' => $meeting->status->value === 'scheduled',
                                'bg-green-100 text-green-800' => $meeting->status->value === 'completed',
                                'bg-red-100 text-red-800' => $meeting->status->value === 'cancelled',
                            ])>{{ $meeting->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <div class="flex gap-2 justify-center text-xs">
                                <a href="{{ route('admin.faith-meetings.show', $meeting) }}" class="text-emerald-700 hover:underline font-bold">عرض / حضور</a>
                                <a href="{{ route('admin.faith-meetings.edit', $meeting) }}" class="text-gray-500 hover:underline">تعديل</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">لا توجد لقاءات في هذا التصنيف</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">{{ $meetings->links() }}</div>
    </div>
</div>
@endsection
