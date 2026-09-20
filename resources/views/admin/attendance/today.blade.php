@extends('layouts.app')

@section('title', 'حضور اليوم')

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
    $tabs = [
        'attended' => ['label' => 'حضر', 'count' => $summary['attended']],
        'all' => ['label' => 'الكل', 'count' => $studentsCount],
        'present' => ['label' => 'حاضر', 'count' => $summary['present']],
        'late' => ['label' => 'متأخر', 'count' => $summary['late']],
        'absent' => ['label' => 'غائب', 'count' => $summary['absent']],
        'excused' => ['label' => 'إذن', 'count' => $summary['excused']],
    ];
@endphp

<div class="bg-white rounded-xl shadow overflow-hidden mb-6 p-4">
    <form method="GET" action="{{ route('admin.attendance.today') }}" class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">التاريخ</label>
            <input type="date" name="date" value="{{ $date }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
        </div>
        <input type="hidden" name="status" value="{{ $status }}">
        <div>
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-4 py-2 rounded-lg w-full">عرض</button>
        </div>
    </form>
</div>

<div class="mb-4 flex items-center justify-between flex-wrap gap-2">
    <h2 class="text-lg font-bold text-gray-800">
        حضور اليوم حسب الصف
        <span class="text-sm font-normal text-gray-500">{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l d F Y') }}</span>
    </h2>
    <span class="text-sm text-gray-500">إجمالي طلاب الجامع: {{ $studentsCount }}</span>
</div>

<x-attendance-summary :summary="$summary" />

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="p-4 border-b flex items-center gap-2 flex-wrap">
        @foreach($tabs as $key => $tab)
            <a href="{{ route('admin.attendance.today', array_filter(['date' => $date, 'status' => $key])) }}"
               @class([
                   'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold transition',
                   'bg-emerald-700 text-white' => $status === $key,
                   'bg-gray-100 text-gray-600 hover:bg-gray-200' => $status !== $key,
               ])>
                {{ $tab['label'] }}
                <span @class([
                    'rounded-full px-1.5 text-[10px] tabular-nums',
                    'bg-white/20 text-white' => $status === $key,
                    'bg-white text-gray-500' => $status !== $key,
                ])>{{ $tab['count'] }}</span>
            </a>
        @endforeach
        <span class="text-xs text-gray-400 ms-auto">اضغط على الصف لعرض شعبه، ثم على الشعبة لعرض طلابها</span>
    </div>

    <div class="p-3 sm:p-4 space-y-2.5">
        @forelse($tree as $node)
            <div data-attendance-tree class="rounded-xl border border-gray-200 overflow-hidden">
                <button type="button" data-attendance-tree-toggle aria-expanded="false"
                        class="w-full flex items-center justify-between gap-3 px-4 py-3.5 bg-gray-50 hover:bg-gray-100 transition text-right">
                    <span class="flex items-center gap-2.5 min-w-0">
                        <span data-attendance-tree-chevron class="w-7 h-7 rounded-lg bg-white border border-gray-200 grid place-items-center text-gray-500 transition-transform shrink-0">
                            <x-icon name="chevron" class="w-4 h-4" />
                        </span>
                        <span class="font-black text-gray-800 truncate">{{ $node['classroom']?->name ?? 'بدون صف' }}</span>
                    </span>
                    <x-attendance-tree-counts :counts="$node['counts']" />
                </button>

                <div data-attendance-tree-panel class="hidden border-t border-gray-100">
                    @forelse($node['sections'] as $section)
                        <div data-attendance-tree class="border-b border-gray-100 last:border-b-0">
                            <button type="button" data-attendance-tree-toggle aria-expanded="false"
                                    class="w-full flex items-center justify-between gap-3 px-4 py-3 bg-white hover:bg-gray-50 transition text-right">
                                <span class="flex items-center gap-2.5 min-w-0">
                                    <span data-attendance-tree-chevron class="w-6 h-6 rounded-lg bg-gray-50 border border-gray-200 grid place-items-center text-gray-400 transition-transform shrink-0">
                                        <x-icon name="chevron" class="w-3.5 h-3.5" />
                                    </span>
                                    <span class="font-bold text-gray-700 truncate">{{ $section['section'] ? 'الشعبة '.$section['section']->name : 'غير مصنفين' }}</span>
                                </span>
                                <x-attendance-tree-counts :counts="$section['counts']" />
                            </button>

                            <div data-attendance-tree-panel class="hidden bg-gray-50/50 border-t border-gray-100">
                                <div class="flex items-center justify-between gap-2 px-4 py-2 flex-wrap">
                                    <span class="text-xs text-gray-500">المعروضون: {{ $section['students']->count() }}</span>
                                    @if($section['section'] && $can('attendance.create'))
                                        <a href="{{ route('admin.attendance.create', ['date' => $date, 'section_id' => $section['section']->id]) }}"
                                           class="text-xs font-bold text-emerald-700 hover:underline">تسجيل حضور هذه الشعبة</a>
                                    @endif
                                </div>

                                <div class="overflow-x-auto bg-white border-t border-gray-100">
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="bg-gray-50 text-gray-600">
                                                <th class="px-4 py-2.5 text-right whitespace-nowrap">الطالب</th>
                                                <th class="px-4 py-2.5 text-center whitespace-nowrap">الحالة</th>
                                                <th class="px-4 py-2.5 text-center whitespace-nowrap">وقت الجلسة</th>
                                                <th class="px-4 py-2.5 text-right whitespace-nowrap">سجّله</th>
                                                <th class="px-4 py-2.5 text-right whitespace-nowrap">ملاحظات</th>
                                                <th class="px-4 py-2.5 text-center whitespace-nowrap">إجراء</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($section['students'] as $row)
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-2.5 border-t font-bold whitespace-nowrap">{{ $row['student']->name }}</td>
                                                    <td class="px-4 py-2.5 border-t text-center">
                                                        @if($row['record'])
                                                            <x-attendance-status-badge :status="$row['record']->status" />
                                                        @else
                                                            <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-gray-100 text-gray-500 whitespace-nowrap">لم يُسجَّل</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-4 py-2.5 border-t text-center whitespace-nowrap text-gray-500" dir="ltr">
                                                        {{ $row['record']?->session?->starts_at ? substr($row['record']->session->starts_at, 0, 5) : '—' }}
                                                    </td>
                                                    <td class="px-4 py-2.5 border-t whitespace-nowrap">{{ $row['record']?->session?->createdBy?->name ?? '—' }}</td>
                                                    <td class="px-4 py-2.5 border-t text-gray-500 max-w-[220px] truncate" title="{{ $row['record']?->note }}">{{ $row['record']?->note ?? '—' }}</td>
                                                    <td class="px-4 py-2.5 border-t text-center whitespace-nowrap">
                                                        @if($can('students.view'))
                                                            <a href="{{ route('admin.students.show', $row['student']) }}" class="text-emerald-700 hover:underline">ملف الطالب</a>
                                                        @else
                                                            <span class="text-gray-300">—</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">لا يوجد طلاب مطابقون للفلتر الحالي</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-4 py-6 text-center text-sm text-gray-500">لا توجد شعب في هذا الصف</div>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="py-10 text-center text-gray-500">لا توجد صفوف بعد</div>
        @endforelse
    </div>

    <div class="px-4 py-3 border-t bg-gray-50 text-xs text-gray-500">
        قاعدة الاحتساب: نسبة الحضور = (حاضر + متأخر) ÷ إجمالي طلاب الجامع ({{ $studentsCount }})؛ غائب لا يحتسب حضوراً، وإذن مستبعد من المقام والبسط.
    </div>
</div>

<div class="mt-4 flex gap-3 flex-wrap">
    <a href="{{ route('admin.attendance.summary', ['from' => $date, 'to' => $date]) }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">ملخص الطلاب لهذا اليوم</a>
    <a href="{{ route('admin.attendance.index', ['date' => $date]) }}" class="bg-gray-700 hover:bg-gray-800 text-white text-sm font-bold px-4 py-2 rounded-lg">جلسات اليوم</a>
    @if($can('attendance.create'))
        <a href="{{ route('admin.attendance.create', ['date' => $date]) }}" class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-sm font-bold px-4 py-2 rounded-lg">تسجيل حضور جديد</a>
    @endif
</div>
@endsection
