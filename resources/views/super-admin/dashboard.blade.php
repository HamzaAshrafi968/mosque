@extends('layouts.app')

@section('title', 'لوحة مدير الجوامع')

@section('content')
@php
    $firstName = preg_split('/\s+/u', trim(auth()->user()->name), 2)[0] ?? auth()->user()->name;
    $statusLabels = ['active' => 'نشط', 'inactive' => 'موقوف', 'archived' => 'مؤرشف'];
@endphp

{{-- ===== ترحيب ===== --}}
<section class="relative overflow-hidden rounded-[28px] gradient-sidebar text-white p-7 sm:p-9 mb-8 reveal">
    <div class="sidebar-pattern absolute inset-0 pointer-events-none"></div>
    <div aria-hidden="true" class="absolute -top-16 -end-10 w-72 h-72 rounded-full bg-gold-300/15 blur-3xl pointer-events-none"></div>

    <div class="relative">
        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold-300/30 bg-gold-400/10 px-3.5 py-1.5 text-[11px] font-bold text-gold-200 mb-4">
            <span class="pulse-dot w-1.5 h-1.5 rounded-full bg-gold-300"></span>
            الإدارة المركزية — جميع الجوامع
        </span>
        <h1 class="text-2xl sm:text-4xl font-black leading-snug">أهلاً بك، {{ $firstName }}</h1>
        <p class="text-emerald-50/70 mt-2.5 text-sm font-medium flex items-center gap-2">
            <x-icon name="globe" class="w-4 h-4 text-gold-300/80" />
            الإشراف المركزي على {{ $totals['mosques'] }} جامعاً و {{ $totals['students'] }} طالباً
        </p>
    </div>
</section>

{{-- ===== المؤشرات ===== --}}
<div class="grid grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-5 mb-8">
    <div class="reveal rd-1 group relative overflow-hidden rounded-2xl bg-white p-5 border border-pine-950/[0.06] card-hover shadow-[0_1px_3px_rgba(5,32,25,0.05)]">
        <span class="w-11 h-11 rounded-xl grid place-items-center text-white shadow-lg bg-gradient-to-br from-gold-400 to-gold-700 group-hover:scale-110 transition-all duration-300"><x-icon name="mosque" class="w-5 h-5" /></span>
        <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-black text-pine-950 tabular-nums" data-count-up data-to="{{ $totals['mosques'] }}">{{ $totals['mosques'] }}</div>
            <div class="text-[13px] text-gray-500 font-bold mt-1">الجوامع المسجلة</div>
        </div>
    </div>
    <div class="reveal rd-2 group relative overflow-hidden rounded-2xl bg-white p-5 border border-pine-950/[0.06] card-hover shadow-[0_1px_3px_rgba(5,32,25,0.05)]">
        <span class="w-11 h-11 rounded-xl grid place-items-center text-white shadow-lg bg-gradient-to-br from-emerald-400 to-emerald-700 group-hover:scale-110 transition-all duration-300"><x-icon name="students" class="w-5 h-5" /></span>
        <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-black text-pine-950 tabular-nums" data-count-up data-to="{{ $totals['students'] }}">{{ $totals['students'] }}</div>
            <div class="text-[13px] text-gray-500 font-bold mt-1">إجمالي الطلاب</div>
        </div>
    </div>
    <div class="reveal rd-3 group relative overflow-hidden rounded-2xl bg-white p-5 border border-pine-950/[0.06] card-hover shadow-[0_1px_3px_rgba(5,32,25,0.05)]">
        <span class="w-11 h-11 rounded-xl grid place-items-center text-white shadow-lg bg-gradient-to-br from-pine-500 to-pine-800 group-hover:scale-110 transition-all duration-300"><x-icon name="teachers" class="w-5 h-5" /></span>
        <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-black text-pine-950 tabular-nums" data-count-up data-to="{{ $totals['teachers'] }}">{{ $totals['teachers'] }}</div>
            <div class="text-[13px] text-gray-500 font-bold mt-1">المعلمون</div>
        </div>
    </div>
    <div class="reveal rd-4 group relative overflow-hidden rounded-2xl bg-white p-5 border border-pine-950/[0.06] card-hover shadow-[0_1px_3px_rgba(5,32,25,0.05)]">
        <span class="w-11 h-11 rounded-xl grid place-items-center text-white shadow-lg bg-gradient-to-br from-teal-400 to-pine-700 group-hover:scale-110 transition-all duration-300"><x-icon name="attendance" class="w-5 h-5" /></span>
        <div class="mt-4">
            <div class="text-2xl sm:text-3xl font-black text-pine-950 tabular-nums" data-count-up data-to="{{ $totals['today_attendance'] }}">{{ $totals['today_attendance'] }}</div>
            <div class="text-[13px] text-gray-500 font-bold mt-1">حضور اليوم</div>
        </div>
    </div>
</div>

{{-- ===== جدول الجوامع ===== --}}
<div class="reveal rd-2 rounded-2xl bg-white border border-pine-950/[0.06] shadow-[0_1px_3px_rgba(5,32,25,0.05)] overflow-hidden">
    <div class="px-6 py-4 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100">
        <h3 class="font-black text-pine-950 flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-lg bg-gold-50 text-gold-600 grid place-items-center"><x-icon name="building" class="w-4 h-4" /></span>
            سجل الجوامع
        </h3>
        <a href="{{ route('super-admin.mosques.create') }}" class="btn-shine inline-flex items-center gap-1.5 bg-gradient-to-l from-pine-700 to-emerald-600 hover:from-pine-800 hover:to-emerald-700 text-white text-sm font-bold px-4 py-2 rounded-xl shadow-md shadow-emerald-700/20 transition-all hover:-translate-y-0.5 active:translate-y-0">
            <x-icon name="plus" class="w-4 h-4" />
            جامع جديد
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gradient-to-l from-gray-50 to-white text-gray-500">
                    <th class="px-6 py-3.5 text-right font-bold whitespace-nowrap">الجامع</th>
                    <th class="px-4 py-3.5 text-right font-bold">الطلاب</th>
                    <th class="px-4 py-3.5 text-right font-bold">المعلمون</th>
                    <th class="px-4 py-3.5 text-right font-bold">الصفوف</th>
                    <th class="px-4 py-3.5 text-right font-bold">بانتظار الاعتماد</th>
                    <th class="px-4 py-3.5 text-right font-bold">الحالة</th>
                    <th class="px-4 py-3.5 text-right font-bold">إجراءات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($mosques as $mosque)
                    <tr class="hover:bg-gold-50/40 transition-colors duration-200">
                        <td class="px-6 py-3.5">
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 rounded-xl p-[1px] bg-gradient-to-br from-gold-300 to-gold-600 shrink-0">
                                    <span class="w-full h-full rounded-[11px] bg-pine-900 grid place-items-center text-gold-300 font-black text-sm">{{ mb_substr($mosque->name, 0, 1) }}</span>
                                </span>
                                <div class="min-w-0">
                                    <div class="font-black text-pine-950 truncate">{{ $mosque->name }}</div>
                                    <div class="text-[11px] text-gray-400 font-semibold">{{ $mosque->code }} · {{ $mosque->users_count }} مستخدم</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3.5 font-bold text-gray-700 tabular-nums">{{ $mosque->students_count }}</td>
                        <td class="px-4 py-3.5 font-bold text-gray-700 tabular-nums">{{ $mosque->teachers_count }}</td>
                        <td class="px-4 py-3.5 font-bold text-gray-700 tabular-nums">{{ $mosque->classrooms_count }}</td>
                        <td class="px-4 py-3.5">
                            @if($mosque->pending_approvals > 0)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-black bg-amber-50 text-amber-700 border border-amber-200/70">
                                    <span class="pulse-dot w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    {{ $mosque->pending_approvals }}
                                </span>
                            @else
                                <span class="text-gray-300 font-bold">0</span>
                            @endif
                        </td>
                        <td class="px-4 py-3.5">
                            <span @class([
                                'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-black',
                                'bg-green-50 text-green-700 border border-green-200/70' => $mosque->status === 'active',
                                'bg-red-50 text-red-600 border border-red-200/70' => $mosque->status === 'inactive',
                                'bg-gray-100 text-gray-500' => $mosque->status === 'archived',
                            ])>
                                <span class="w-1.5 h-1.5 rounded-full @if($mosque->status === 'active') bg-green-500 @elseif($mosque->status === 'inactive') bg-red-400 @else bg-gray-400 @endif"></span>
                                {{ $statusLabels[$mosque->status] ?? $mosque->status }}
                            </span>
                        </td>
                        <td class="px-4 py-3.5">
                            <div class="flex flex-wrap gap-1.5">
                                <form method="POST" action="{{ route('super-admin.mosques.enter', $mosque) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-gradient-to-l from-pine-600 to-emerald-600 text-white hover:from-pine-700 hover:to-emerald-700 rounded-lg text-xs font-bold shadow-sm shadow-emerald-600/20 transition-all hover:-translate-y-0.5">
                                        <x-icon name="logout" class="w-3.5 h-3.5 rotate-180" />
                                        الدخول للجامع
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('super-admin.mosques.enter', $mosque) }}">
                                    @csrf
                                    <input type="hidden" name="to" value="attendance">
                                    <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-lg text-xs font-bold transition">
                                        <x-icon name="attendance" class="w-3.5 h-3.5" />
                                        الحضور والغياب
                                    </button>
                                </form>
                                <a href="{{ route('super-admin.mosques.users.index', $mosque) }}" class="px-2.5 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded-lg text-xs font-bold transition">المستخدمون</a>
                                <a href="{{ route('super-admin.mosques.roles.index', $mosque) }}" class="px-2.5 py-1.5 bg-sky-50 text-sky-700 hover:bg-sky-100 rounded-lg text-xs font-bold transition">الأدوار</a>
                                <a href="{{ route('super-admin.mosques.edit', $mosque) }}" class="px-2.5 py-1.5 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg text-xs font-bold transition">تعديل</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
