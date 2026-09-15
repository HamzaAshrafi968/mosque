@extends('layouts.app')

@section('title', 'رواتب المعلمين')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">رواتب المعلمين</h2>
            <p class="text-sm text-gray-500 mt-1">
                حدد الراتب الشهري لكل أستاذ، وراقب ساعات العمل الشهرية — كل دفعة تُصفّر عدّاد الساعات
                (يبدأ العدّ من اليوم التالي لآخر دفعة).
            </p>
        </div>
        <a href="{{ route('admin.work-hours.index') }}" class="text-sm text-emerald-700 hover:underline">إدارة ساعات العمل ←</a>
    </div>

    <form method="GET" action="{{ route('admin.payroll.index') }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
        <div class="md:col-span-2">
            <label class="block text-xs font-bold text-gray-600 mb-1">بحث بالاسم</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="اسم المعلم" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-600 mb-1">الشهر</label>
            <input type="month" name="month" value="{{ $monthInput }}" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">عرض</button>
    </form>

    @if($teachers->isEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 text-center text-gray-400 font-bold">لا يوجد معلمون مطابقون</div>
    @else
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-5">
            @foreach($teachers as $teacher)
                @php
                    $summary = $summaries[$teacher->id] ?? null;
                    $salary = $teacher->monthly_salary !== null ? (float) $teacher->monthly_salary : null;
                    $lastPayment = $summary['last_payment'] ?? null;
                @endphp
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 bg-gray-50/60">
                        <div>
                            <a href="{{ route('admin.teachers.show', $teacher) }}" class="font-black text-gray-800 hover:text-emerald-700">{{ $teacher->name }}</a>
                            <div class="flex flex-wrap items-center gap-1 mt-1">
                                @forelse($teacher->studySessions as $session)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-teal-100 text-teal-800">{{ $session->name }}</span>
                                @empty
                                    @if($teacher->studySession)
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-teal-100 text-teal-800">{{ $teacher->studySession->name }}</span>
                                    @endif
                                @endforelse
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-[11px] font-bold',
                                    'bg-green-100 text-green-800' => $teacher->is_active,
                                    'bg-red-100 text-red-800' => ! $teacher->is_active,
                                ])>{{ $teacher->is_active ? 'نشط' : 'غير نشط' }}</span>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                الراتب:
                                @if($salary !== null)
                                    <span class="font-black text-gray-700" dir="ltr">{{ number_format($salary, 2) }} {{ $currency }}</span>
                                @else
                                    <span class="text-gray-300">غير محدد</span>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('admin.teachers.work-hours.index', $teacher) }}" class="text-xs font-bold text-emerald-700 hover:underline">ساعات العمل ←</a>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-x-reverse divide-gray-100 border-b border-gray-100 text-center">
                        <div class="p-3">
                            <div class="text-lg font-black text-emerald-700">{{ $summary['monthly_hours'] ?? 0 }}</div>
                            <div class="text-[11px] text-gray-500 mt-0.5">ساعات {{ \App\Support\QuranProgramSettings::monthLabel($month->format('Y-m')) }}</div>
                        </div>
                        <div class="p-3">
                            <div @class(['text-lg font-black', ($summary['hours_since_last_payment'] ?? 0) > 0 ? 'text-amber-600' : 'text-gray-400'])>
                                {{ $summary['hours_since_last_payment'] ?? 0 }}
                            </div>
                            <div class="text-[11px] text-gray-500 mt-0.5">ساعات منذ آخر دفعة</div>
                        </div>
                        <div class="p-3">
                            <div class="text-lg font-black text-gray-800" dir="ltr">{{ number_format($summary['paid_in_month'] ?? 0, 2) }}</div>
                            <div class="text-[11px] text-gray-500 mt-0.5">مدفوع هذا الشهر</div>
                        </div>
                        <div class="p-3">
                            @if($lastPayment)
                                <div class="text-sm font-black text-gray-800" dir="ltr">{{ number_format((float) $lastPayment->amount, 2) }}</div>
                                <div class="text-[11px] text-gray-500 mt-0.5">آخر دفعة {{ $lastPayment->created_at->format('Y-m-d') }}</div>
                            @else
                                <div class="text-sm font-black text-gray-300">—</div>
                                <div class="text-[11px] text-gray-500 mt-0.5">لا دفعات بعد</div>
                            @endif
                        </div>
                    </div>

                    <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <form method="POST" action="{{ route('admin.payroll.salary', $teacher) }}" class="space-y-2">
                            @csrf
                            <label class="block text-xs font-bold text-gray-600">الراتب الشهري ({{ $currency }})</label>
                            <div class="flex gap-2">
                                <input type="number" step="0.01" min="0" name="monthly_salary"
                                       value="{{ old('monthly_salary', $salary) }}" placeholder="غير محدد"
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" dir="ltr">
                                <button type="submit" class="shrink-0 bg-gray-800 hover:bg-gray-900 text-white text-xs font-bold px-3 py-2 rounded-lg">حفظ</button>
                            </div>
                            <p class="text-[11px] text-gray-400">ما تعطيه للأستاذ في الشهر.</p>
                        </form>

                        <form method="POST" action="{{ route('admin.payroll.pay', $teacher) }}" class="space-y-2">
                            @csrf
                            <label class="block text-xs font-bold text-gray-600">تسجيل دفعة (تُصفّر عدّاد الساعات)</label>
                            <div class="flex gap-2">
                                <input type="number" step="0.01" min="0.01" name="amount"
                                       value="{{ old('amount', $salary) }}" placeholder="المبلغ" required
                                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" dir="ltr">
                                <button type="submit" class="shrink-0 bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-3 py-2 rounded-lg">دفع</button>
                            </div>
                            <input type="text" name="description" maxlength="1000" placeholder="بيان الدفعة (اختياري)"
                                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <p class="text-[11px] text-gray-400">
                                @if($teacher->user_id)
                                    سيصل إشعار للأستاذ فوراً.
                                @else
                                    الأستاذ بلا حساب دخول — لن يصل إشعار.
                                @endif
                            </p>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
        <div>{{ $teachers->links() }}</div>
    @endif
</div>
@endsection
