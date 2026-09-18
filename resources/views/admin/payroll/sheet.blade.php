@extends('layouts.app')

@section('title', 'كشف راتب ' . $teacher->name)

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
    $period = $summary['period'];
    $closed = $summary['status'] === \App\Enums\PayrollStatus::Closed;
@endphp

<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <a href="{{ route('admin.payroll.index', ['month' => $monthInput]) }}" class="text-sm text-emerald-700 hover:text-emerald-800">← رواتب المعلمين</a>
            <h2 class="text-2xl font-extrabold text-gray-800 mt-1">كشف راتب — {{ $teacher->name }}</h2>
            <p class="text-sm text-gray-500 mt-1">
                {{ \App\Support\QuranProgramSettings::monthLabel($monthInput) }}
                <span class="mx-2 text-gray-300">|</span>
                {{ $summary['pay_type']->label() }}
                <span class="ms-2 px-2 py-0.5 rounded-full text-[11px] font-bold {{ $summary['state']->badgeClasses() }}">{{ $summary['state']->label() }}</span>
                @if($closed)
                    <span class="ms-2 px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-200 text-gray-600">مغلق</span>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <form method="GET" action="{{ route('admin.payroll.sheet', $teacher) }}" class="flex items-center gap-2">
                <input type="month" name="month" value="{{ $monthInput }}" class="border border-gray-300 rounded-lg px-3 py-1.5 text-sm">
                <button class="text-xs font-bold text-gray-600 hover:text-gray-800">عرض الشهر</button>
            </form>
            <a href="{{ route('admin.teachers.timesheet.index', ['teacher' => $teacher, 'month' => $monthInput]) }}" class="text-sm text-gray-500 hover:underline">كشف العمل</a>
            <a href="{{ route('admin.payroll.export', ['month' => $monthInput]) }}" class="text-sm text-gray-500 hover:underline">تصدير CSV</a>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 divide-x divide-x-reverse divide-gray-100 bg-white rounded-2xl shadow-sm border border-gray-200 text-center overflow-hidden">
        <div class="p-4">
            <div class="text-lg font-black text-emerald-700">{{ \App\Models\WorkSlot::formatMinutes($summary['total_minutes']) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">الفعلي</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-gray-500">{{ \App\Models\WorkSlot::formatMinutes($summary['planned_minutes']) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">المخطط</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-gray-800" dir="ltr">
                @if($summary['pay_type'] === \App\Enums\PayType::Hourly)
                    {{ $summary['hourly_rate'] !== null ? number_format($summary['hourly_rate'], 2) : ($summary['breakdown'] !== [] ? 'متعدد' : '—') }}
                @else
                    {{ $summary['monthly_salary'] !== null ? number_format($summary['monthly_salary'], 2) : '—' }}
                @endif
            </div>
            <div class="text-[11px] text-gray-500 mt-0.5">{{ $summary['pay_type'] === \App\Enums\PayType::Hourly ? 'سعر الساعة' : 'الراتب الشهري' }}</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-gray-800" dir="ltr">{{ number_format($summary['gross'], 2) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">الإجمالي</div>
        </div>
        <div class="p-4">
            <div class="text-lg font-black text-emerald-700" dir="ltr">{{ number_format($summary['paid'], 2) }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">المدفوع</div>
        </div>
        <div class="p-4">
            <div @class(['text-lg font-black', 'text-amber-600' => $summary['remaining'] > 0, 'text-gray-300' => $summary['remaining'] <= 0]) dir="ltr">
                {{ number_format($summary['remaining'], 2) }}
            </div>
            <div class="text-[11px] text-gray-500 mt-0.5">المتبقي</div>
        </div>
    </div>

    @if($summary['missing_rates'] !== [])
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <span class="font-bold">لا يمكن الاحتساب كاملاً:</span> لا يوجد سعر ساعة في
            <span dir="ltr">{{ implode('، ', $summary['missing_rates']) }}</span>
            @if($can('hourly_rates.manage'))
                — <a href="{{ route('admin.payroll.rates.index', ['q' => $teacher->name]) }}" class="underline font-bold">إضافة سعر</a>
            @endif
        </div>
    @endif

    @if($summary['pay_type'] === \App\Enums\PayType::Hourly && count($summary['breakdown']) > 1)
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-black text-pine-950 mb-3">تفصيل التسعير (تغيّر السعر داخل الشهر)</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600">
                            <th scope="col" class="px-4 py-2 text-right">السعر</th>
                            <th scope="col" class="px-4 py-2 text-right">الدقائق</th>
                            <th scope="col" class="px-4 py-2 text-right">من</th>
                            <th scope="col" class="px-4 py-2 text-right">إلى</th>
                            <th scope="col" class="px-4 py-2 text-right">القيمة</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($summary['breakdown'] as $segment)
                        <tr class="border-t">
                            <td class="px-4 py-2 font-bold" dir="ltr">{{ number_format($segment['rate'], 2) }}</td>
                            <td class="px-4 py-2">{{ \App\Models\WorkSlot::formatMinutes($segment['minutes']) }}</td>
                            <td class="px-4 py-2" dir="ltr">{{ $segment['from'] }}</td>
                            <td class="px-4 py-2" dir="ltr">{{ $segment['to'] }}</td>
                            <td class="px-4 py-2 font-bold" dir="ltr">{{ number_format($segment['minutes'] / 60 * $segment['rate'], 2) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        @if($can('payroll.manage'))
            <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-3">
                <h3 class="font-black text-pine-950">بيانات الأجر</h3>
                <form method="POST" action="{{ route('admin.payroll.salary', $teacher) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">نوع الأجر</label>
                        <select name="pay_type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            @foreach(\App\Enums\PayType::cases() as $type)
                                <option value="{{ $type->value }}" @selected($summary['pay_type'] === $type)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">الراتب الشهري ({{ $currency }}) — للنوع الشهري</label>
                        <input type="number" step="0.01" min="0" name="monthly_salary"
                               value="{{ old('monthly_salary', $summary['monthly_salary']) }}" placeholder="غير محدد"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" dir="ltr">
                    </div>
                    <button class="bg-gray-800 hover:bg-gray-900 text-white text-sm font-bold px-4 py-2 rounded-lg">حفظ بيانات الأجر</button>
                </form>
                <p class="text-[11px] text-gray-400">أسعار الساعة تُدار من <a href="{{ route('admin.payroll.rates.index') }}" class="text-emerald-700 underline">سجل الأسعار</a>.</p>
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-3">
            <h3 class="font-black text-pine-950">حالة الكشف</h3>

            @if($closed)
                <p class="text-sm text-gray-500">الشهر مغلق — اللقطة ثابتة ولا تتأثر بتغيير الفترات أو الأسعار.</p>
                @if($can('payroll.reopen'))
                    <form method="POST" action="{{ route('admin.payroll.reopen', $teacher) }}" class="space-y-2"
                          onsubmit="return confirm('إعادة فتح الكشف تسمح بتعديل الفترات. متابعة؟')">
                        @csrf
                        <input type="hidden" name="month" value="{{ $monthInput }}">
                        <input type="text" name="reason" required maxlength="500" placeholder="سبب إعادة الفتح (إلزامي)"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <button class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold px-4 py-2 rounded-lg">إعادة فتح الشهر</button>
                    </form>
                @endif
            @else
                <p class="text-sm text-gray-500">الشهر مفتوح — تُحتسب القيم حياً، وعند الإغلاق تُثبَّت اللقطة.</p>
                @if($can('payroll.close'))
                    <form method="POST" action="{{ route('admin.payroll.close', $teacher) }}"
                          onsubmit="return confirm('إغلاق كشف الشهر؟ لن يمكن تعديل فتراته بعد ذلك.')">
                        @csrf
                        <input type="hidden" name="month" value="{{ $monthInput }}">
                        <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">إغلاق الكشف</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-black text-pine-950">الدفعات</h3>
            <span class="text-xs text-gray-500">مدفوع هذا الشهر: <span class="font-bold" dir="ltr">{{ number_format($summary['paid'], 2) }}</span> {{ $currency }}</span>
        </div>

        @if($can('payroll.pay'))
            <form method="POST" action="{{ route('admin.payroll.pay', $teacher) }}" class="grid grid-cols-1 sm:grid-cols-5 gap-2 items-end">
                @csrf
                <input type="hidden" name="month" value="{{ $monthInput }}">
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 mb-0.5">المبلغ</label>
                    <input type="number" step="0.01" min="0.01" name="amount" required
                           value="{{ old('amount', $summary['remaining'] > 0 ? $summary['remaining'] : '') }}"
                           class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm" dir="ltr">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 mb-0.5">طريقة الدفع</label>
                    <select name="payment_method" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                        <option value="">—</option>
                        <option value="نقد">نقد</option>
                        <option value="تحويل بنكي">تحويل بنكي</option>
                        <option value="أخرى">أخرى</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 mb-0.5">مرجع (اختياري)</label>
                    <input type="text" name="reference" maxlength="255" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-gray-500 mb-0.5">بيان (اختياري)</label>
                    <input type="text" name="description" maxlength="1000" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                </div>
                <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تسجيل دفعة</button>
            </form>
            <p class="text-[11px] text-gray-400">الدفع الجزئي مسموح، ولا يُقبل مبلغ يتجاوز المتبقي ({{ number_format($summary['remaining'], 2) }} {{ $currency }}).</p>
        @endif

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th scope="col" class="px-4 py-2 text-right">التاريخ</th>
                        <th scope="col" class="px-4 py-2 text-right">البيان</th>
                        <th scope="col" class="px-4 py-2 text-right">الطريقة</th>
                        <th scope="col" class="px-4 py-2 text-right">المبلغ</th>
                        <th scope="col" class="px-4 py-2 text-right">سجّلها</th>
                        <th scope="col" class="px-4 py-2 text-center">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($payments as $payment)
                    <tr class="border-t">
                        <td class="px-4 py-2 whitespace-nowrap" dir="ltr">{{ $payment->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-2">{{ $payment->description ?: '—' }}</td>
                        <td class="px-4 py-2">{{ $payment->payment_method ?: '—' }}</td>
                        <td class="px-4 py-2 font-bold text-emerald-700" dir="ltr">+{{ number_format((float) $payment->amount, 2) }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $payment->creator?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-center">
                            @if($payment->reversal)
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-red-100 text-red-800">معكوسة</span>
                            @else
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">مُودعة</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">لا دفعات مرتبطة بهذا الكشف بعد</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($auditLogs->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <h3 class="font-black text-pine-950 mb-3">سجل العمليات على الكشف</h3>
            <div class="space-y-2">
                @foreach($auditLogs as $log)
                    <div class="flex flex-wrap items-center justify-between gap-2 text-xs border-b border-gray-50 pb-2">
                        <span class="font-bold text-gray-700">{{ $log->action }}</span>
                        <span class="text-gray-400">{{ $log->user?->name ?? '—' }} — {{ $log->created_at->format('Y-m-d H:i') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
