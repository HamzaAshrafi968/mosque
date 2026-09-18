@extends('layouts.app')

@section('title', 'كشوف العمل')

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
    $canManage = $can('work_hours.manage');
    $isDaily = $view === 'daily';
    $isWeekly = $view === 'weekly';
    $isMonthly = $view === 'monthly';
    $baseQuery = array_filter([
        'view' => $view,
        'date' => $dateInput,
        'month' => $monthInput,
        'q' => $search !== '' ? $search : null,
        'session' => $sessionId,
    ]);
@endphp

<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">كشوف العمل</h2>
            <p class="text-sm text-gray-500 mt-1">
                فترات العمل الفعلية (من ساعة → إلى ساعة) — يومي وأسبوعي وشهري، والإجماليات محسوبة تلقائياً.
            </p>
        </div>
        <div class="flex items-center gap-3 text-sm">
            @if($can('work_hours.view'))
                <a href="{{ route('admin.work-hours.index') }}" class="text-gray-500 hover:underline">الجدول الأسبوعي (المخطط) ←</a>
            @endif
            @if($can('payroll.view') || $can('finance.view'))
                <a href="{{ route('admin.payroll.index') }}" class="font-bold text-emerald-700 hover:underline">رواتب المعلمين ←</a>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="flex flex-wrap border-b border-gray-100">
            @foreach(['daily' => 'يومي', 'weekly' => 'أسبوعي', 'monthly' => 'شهري'] as $key => $label)
                <a href="{{ route('admin.timesheet.index', array_merge($baseQuery, ['view' => $key])) }}"
                   @class([
                       'px-5 py-3 text-sm font-bold border-b-2 -mb-px',
                       'border-emerald-700 text-emerald-800' => $view === $key,
                       'border-transparent text-gray-500 hover:text-gray-700' => $view !== $key,
                   ])>{{ $label }}</a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.timesheet.index') }}"
              class="p-4 grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
            <input type="hidden" name="view" value="{{ $view }}">

            <div class="md:col-span-2">
                <label class="block text-xs font-bold text-gray-600 mb-1">بحث بالاسم</label>
                <input type="text" name="q" value="{{ $search }}" placeholder="اسم المعلم"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-600 mb-1">الدوام</label>
                <select name="session" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">كل الدوامات</option>
                    @foreach($sessions as $session)
                        <option value="{{ $session->id }}" @selected((string) $sessionId === (string) $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
            </div>

            @if($isMonthly)
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">الشهر</label>
                    <input type="month" name="month" value="{{ $monthInput }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            @else
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">{{ $isWeekly ? 'أي يوم من الأسبوع' : 'التاريخ' }}</label>
                    <input type="date" name="date" value="{{ $dateInput }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" dir="ltr">
                </div>
            @endif

            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">عرض</button>
        </form>
    </div>

    @if($canManage)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-black text-pine-950 mb-3">تسجيل فترة عمل</h3>
            <x-work-slot-form
                :action="route('admin.timesheet.slots.store')"
                :teachers="$teachers->getCollection()"
                :date="$isMonthly ? $month->startOfMonth()->toDateString() : $dateInput"
                save-and-add />
        </div>
    @endif

    @if($teachers->isEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center text-gray-400 font-bold">لا يوجد معلمون مطابقون</div>
    @else
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                @if($isDaily)
                    <table class="w-full text-sm">
                        <caption class="sr-only">فترات العمل في {{ $dateInput }}</caption>
                        <thead>
                            <tr class="bg-gray-50 text-gray-600">
                                <th scope="col" class="px-4 py-3 text-right">المعلم</th>
                                <th scope="col" class="px-4 py-3 text-right">فترات اليوم</th>
                                <th scope="col" class="px-4 py-3 text-right">الإجمالي</th>
                                <th scope="col" class="px-4 py-3 text-center">إجراء</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($teachers as $teacher)
                            @php $daySlots = $slotsByTeacher->get($teacher->id, collect()); @endphp
                            <tr class="border-t">
                                <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-800">{{ $teacher->name }}</td>
                                <td class="px-4 py-3">
                                    @forelse($daySlots as $slot)
                                        <span class="inline-flex items-center gap-1 text-xs bg-pine-50 text-pine-800 rounded-lg px-2 py-1 me-1 mb-1" dir="ltr">
                                            {{ substr($slot->start_time, 0, 5) }} — {{ substr($slot->end_time, 0, 5) }}
                                        </span>
                                    @empty
                                        <span class="text-gray-300">لا فترات</span>
                                    @endforelse
                                </td>
                                <td class="px-4 py-3">
                                    <x-duration :minutes="$aggregates[$teacher->id]['total_minutes'] ?? 0" class="font-bold" />
                                </td>
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    <a href="{{ route('admin.teachers.timesheet.index', ['teacher' => $teacher, 'month' => $date->format('Y-m')]) }}"
                                       class="text-emerald-700 hover:underline text-xs font-bold">فتح الكشف</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @elseif($isWeekly)
                    <table class="w-full text-sm">
                        <caption class="sr-only">الشبكة الأسبوعية {{ $weekStart->format('Y-m-d') }} — {{ $weekEnd->format('Y-m-d') }}</caption>
                        <thead>
                            <tr class="bg-gray-50 text-gray-600">
                                <th scope="col" class="px-4 py-3 text-right">المعلم</th>
                                @foreach($weekDays as $day)
                                    <th scope="col" class="px-2 py-3 text-center whitespace-nowrap">
                                        <div>{{ \App\Enums\WorkDay::from($day->dayOfWeek)->label() }}</div>
                                        <div class="text-[10px] text-gray-400 font-normal" dir="ltr">{{ $day->format('m-d') }}</div>
                                    </th>
                                @endforeach
                                <th scope="col" class="px-4 py-3 text-right">الإجمالي</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($teachers as $teacher)
                            @php $byDate = $aggregates[$teacher->id]['by_date'] ?? []; @endphp
                            <tr class="border-t">
                                <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-800">{{ $teacher->name }}</td>
                                @foreach($weekDays as $day)
                                    @php $minutes = (int) ($byDate[$day->toDateString()] ?? 0); @endphp
                                    <td class="px-2 py-3 text-center">
                                        <span @class([
                                            'inline-block rounded-lg px-2 py-1 text-xs font-bold',
                                            'bg-emerald-50 text-emerald-700' => $minutes > 0,
                                            'text-gray-300' => $minutes === 0,
                                        ])>{{ $minutes > 0 ? \App\Models\WorkSlot::formatMinutes($minutes) : '—' }}</span>
                                    </td>
                                @endforeach
                                <td class="px-4 py-3">
                                    <x-duration :minutes="$aggregates[$teacher->id]['total_minutes'] ?? 0" class="font-black text-emerald-700" />
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @else
                    <table class="w-full text-sm">
                        <caption class="sr-only">الإجماليات الشهرية {{ $monthInput }}</caption>
                        <thead>
                            <tr class="bg-gray-50 text-gray-600">
                                <th scope="col" class="px-4 py-3 text-right">المعلم</th>
                                @foreach($monthWeeks as $week)
                                    <th scope="col" class="px-2 py-3 text-center whitespace-nowrap">
                                        <div class="text-[11px]">{{ $week['start']->format('j/n') }} – {{ $week['end']->format('j/n') }}</div>
                                    </th>
                                @endforeach
                                <th scope="col" class="px-4 py-3 text-right">الفعلي</th>
                                <th scope="col" class="px-4 py-3 text-right">الإجمالي</th>
                                <th scope="col" class="px-4 py-3 text-right">المدفوع</th>
                                <th scope="col" class="px-4 py-3 text-right">المتبقي</th>
                                <th scope="col" class="px-4 py-3 text-center">الحالة</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($teachers as $teacher)
                            @php
                                $byWeek = $aggregates[$teacher->id]['by_week'] ?? [];
                                $summary = $payrollSummaries[$teacher->id] ?? null;
                            @endphp
                            <tr class="border-t">
                                <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-800">
                                    <a href="{{ route('admin.teachers.timesheet.index', ['teacher' => $teacher, 'month' => $monthInput]) }}"
                                       class="hover:text-emerald-700">{{ $teacher->name }}</a>
                                </td>
                                @foreach($monthWeeks as $week)
                                    @php
                                        $weekKey = \App\Support\TimesheetAggregator::weekStart($week['start'])->toDateString();
                                        $minutes = (int) ($byWeek[$weekKey] ?? 0);
                                    @endphp
                                    <td class="px-2 py-3 text-center text-xs">
                                        <span @class(['font-bold', 'text-pine-800' => $minutes > 0, 'text-gray-300' => $minutes === 0])>
                                            {{ $minutes > 0 ? \App\Models\WorkSlot::formatMinutes($minutes) : '—' }}
                                        </span>
                                    </td>
                                @endforeach
                                <td class="px-4 py-3">
                                    <x-duration :minutes="$summary['total_minutes'] ?? 0" class="font-black text-emerald-700" />
                                </td>
                                <td class="px-4 py-3 font-bold text-gray-800" dir="ltr">{{ number_format($summary['gross'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-gray-600" dir="ltr">{{ number_format($summary['paid'] ?? 0, 2) }}</td>
                                <td class="px-4 py-3 font-bold" dir="ltr">
                                    <span @class(['text-amber-600' => ($summary['remaining'] ?? 0) > 0, 'text-gray-300' => ($summary['remaining'] ?? 0) <= 0])>
                                        {{ number_format($summary['remaining'] ?? 0, 2) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($summary)
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $summary['state']->badgeClasses() }}">{{ $summary['state']->label() }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="p-4 border-t border-gray-100">{{ $teachers->links() }}</div>
        </div>
    @endif
</div>
@endsection
