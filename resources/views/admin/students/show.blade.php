@extends('layouts.app')

@section('title', 'ملف الطالب')

@section('content')
@php
    $stats = $attendanceStats;
    $balance = $finance['balance'] ?? 0;
@endphp

<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="px-4 py-3 bg-emerald-700 text-white flex items-center justify-between">
        <div class="font-bold text-lg">{{ $student->name }}</div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.students.edit', $student) }}" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg">تعديل</a>
            <form method="POST" action="{{ route('admin.students.archive', $student) }}">
                @csrf
                @method('PATCH')
                <button type="submit" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg">
                    {{ $student->status === 'active' ? 'أرشفة' : 'إلغاء الأرشفة' }}
                </button>
            </form>
        </div>
    </div>
    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div><span class="text-sm text-gray-500">الجنس:</span> <span class="font-bold mr-2">{{ $student->gender === 'male' ? 'ذكر' : 'أنثى' }}</span></div>
        <div><span class="text-sm text-gray-500">تاريخ الميلاد:</span> <span class="font-bold mr-2">{{ $student->birth_date?->format('Y-m-d') ?? '—' }}</span></div>
        <div>
            <span class="text-sm text-gray-500">الصف / الشعبة:</span>
            @if($student->section)
                <a href="{{ route('admin.sections.show', $student->section) }}" class="font-bold mr-2 text-emerald-700 hover:underline">{{ $student->classroom?->name }} / {{ $student->section?->name }}</a>
            @else
                <span class="font-bold mr-2 text-gray-400">غير مسجل في شعبة</span>
            @endif
        </div>
        <div>
            <span class="text-sm text-gray-500">الحالة:</span>
            <span @class(['px-2 py-1 rounded-full text-xs font-bold mr-2', 'bg-green-100 text-green-800' => $student->status === 'active', 'bg-gray-100 text-gray-800' => $student->status === 'archived'])>
                {{ $student->status === 'active' ? 'نشط' : 'مؤرشف' }}
            </span>
        </div>
        <div><span class="text-sm text-gray-500">ولي الأمر:</span> <span class="font-bold mr-2">{{ $student->guardian_name ?? '—' }}</span></div>
        <div><span class="text-sm text-gray-500">هاتف ولي الأمر:</span> <span class="font-bold mr-2">{{ $student->guardian_phone ?? '—' }}</span></div>
        @if($student->notes)
            <div class="col-span-2"><span class="text-sm text-gray-500">ملاحظات:</span> <span class="font-bold mr-2">{{ $student->notes }}</span></div>
        @endif
    </div>
</div>

@if($student->status === 'active')
    <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
        <div class="px-4 py-3 bg-emerald-700 text-white font-bold">نقل إلى شعبة أخرى (مع حفظ تاريخ الشعب السابقة)</div>
        <form method="POST" action="{{ route('admin.students.transfer', $student) }}" class="p-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            @csrf
            <div class="md:col-span-2">
                <select name="section_id" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="">اختر الشعبة الجديدة...</option>
                    @foreach($transferTargets as $section)
                        <option value="{{ $section->id }}">{{ $section->classroom?->name }} - {{ $section->name }}</option>
                    @endforeach
                </select>
                @if($transferTargets->isEmpty())
                    <p class="text-xs text-gray-400 mt-1">لا توجد شعب أخرى نشطة</p>
                @endif
            </div>
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-bold px-4 py-2 rounded-lg">نقل الطالب</button>
        </form>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-4 py-3 bg-emerald-700 text-white font-bold">ملخص الحضور (جلسات)</div>
        <div class="p-4 grid grid-cols-4 gap-3 text-center">
            <div class="bg-green-50 rounded-lg p-3">
                <div class="text-2xl font-bold text-green-700">{{ $stats['present'] ?? 0 }}</div>
                <div class="text-xs text-gray-600">حاضر</div>
            </div>
            <div class="bg-red-50 rounded-lg p-3">
                <div class="text-2xl font-bold text-red-700">{{ $stats['absent'] ?? 0 }}</div>
                <div class="text-xs text-gray-600">غائب</div>
            </div>
            <div class="bg-yellow-50 rounded-lg p-3">
                <div class="text-2xl font-bold text-yellow-600">{{ $stats['late'] ?? 0 }}</div>
                <div class="text-xs text-gray-600">متأخر</div>
            </div>
            <div class="bg-sky-50 rounded-lg p-3">
                <div class="text-2xl font-bold text-sky-600">{{ $stats['excused'] ?? 0 }}</div>
                <div class="text-xs text-gray-600">معذور</div>
            </div>
        </div>
        <div class="px-4 pb-4 text-center">
            <div class="text-3xl font-bold text-emerald-700">{{ $stats['percentage'] !== null ? $stats['percentage'].'%' : '—' }}</div>
            <div class="text-xs text-gray-500 mt-1">
                نسبة الحضور (حاضر+متأخر من الجلسات المؤهلة، المعذور مستبعد)
                @if($stats['total'] > 0)
                    — {{ $stats['attended'] }}/{{ $stats['total'] }}
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-4 py-3 bg-emerald-700 text-white font-bold flex justify-between items-center">
            <span>سجل الشعب (العضوية)</span>
            <span class="text-xs font-normal text-emerald-100">التسجيل الحالي: {{ $student->section?->name ?? '—' }}</span>
        </div>
        @if($enrollmentHistory->isEmpty())
            <div class="px-4 py-6 text-center text-gray-500">لا يوجد سجل تسجيل في الشعب</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 text-xs">
                            <th class="px-4 py-2 text-right whitespace-nowrap">الشعبة</th>
                            <th class="px-4 py-2 text-right whitespace-nowrap">الحالة</th>
                            <th class="px-4 py-2 text-right whitespace-nowrap">تاريخ التسجيل</th>
                            <th class="px-4 py-2 text-right whitespace-nowrap">تاريخ الانتهاء</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($enrollmentHistory as $membership)
                            <tr>
                                <td class="px-4 py-2 border-t font-bold whitespace-nowrap">{{ $membership->section->classroom?->name }} / {{ $membership->section->name }}</td>
                                <td class="px-4 py-2 border-t whitespace-nowrap">
                                    <span @class([
                                        'px-2 py-0.5 rounded-full text-xs font-bold',
                                        'bg-green-100 text-green-800' => $membership->status->value === 'active',
                                        'bg-yellow-100 text-yellow-800' => $membership->status->value === 'transferred',
                                        'bg-gray-100 text-gray-600' => $membership->status->value === 'inactive',
                                        'bg-sky-100 text-sky-800' => $membership->status->value === 'completed',
                                    ])>{{ $membership->status->label() }}</span>
                                </td>
                                <td class="px-4 py-2 border-t whitespace-nowrap">{{ $membership->enrolled_at?->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-4 py-2 border-t whitespace-nowrap">{{ $membership->left_at?->format('Y-m-d') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>

@if($customValues->isNotEmpty())
    <div class="bg-white rounded-xl shadow overflow-hidden mb-6">
        <div class="px-4 py-3 bg-emerald-700 text-white font-bold">بيانات إضافية</div>
        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach($customValues as $pair)
                @php
                    $value = $pair['value'];
                    $display = is_bool($value) ? ($value ? 'نعم' : 'لا') : (is_array($value) ? implode('، ', $value) : $value);
                @endphp
                <div>
                    <span class="text-sm text-gray-500">{{ $pair['field']->name }}:</span>
                    <span class="font-bold mr-2">{{ $display }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="px-4 py-3 bg-emerald-700 text-white font-bold flex justify-between items-center">
        <span>💰 الملف المالي</span>
        <a href="{{ route('admin.finance.show', ['personType' => 'student', 'person' => $student]) }}" class="text-xs bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg">عرض كامل</a>
    </div>
    <div class="p-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
        <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-xl font-bold text-gray-800">{{ number_format($finance['charges'], 2) }}</div>
            <div class="text-xs text-gray-600">مستحقات</div>
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-xl font-bold text-emerald-700">{{ number_format($finance['payments'], 2) }}</div>
            <div class="text-xs text-gray-600">مدفوعات</div>
        </div>
        <div class="bg-gray-50 rounded-lg p-3">
            <div class="text-xl font-bold text-sky-700">{{ number_format($finance['received'], 2) }}</div>
            <div class="text-xs text-gray-600">تحويلات مستلمة</div>
        </div>
        <div @class(['rounded-lg p-3', $balance > 0 ? 'bg-red-50' : 'bg-emerald-50'])>
            <div @class(['text-xl font-bold', $balance > 0 ? 'text-red-700' : 'text-emerald-700'])>
                {{ number_format(abs($balance), 2) }}
            </div>
            <div class="text-xs text-gray-600">{{ $balance > 0 ? 'عليه (مطلوب)' : ($balance < 0 ? 'له (رصيد)' : 'رصيد صفري') }}</div>
        </div>
    </div>
    @if($transactions->isNotEmpty())
        <div class="border-t">
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50 text-gray-600 text-xs"><th class="px-4 py-2 text-right">التاريخ</th><th class="px-4 py-2 text-right">النوع</th><th class="px-4 py-2 text-right">البيان</th><th class="px-4 py-2 text-left">المبلغ</th></tr></thead>
                <tbody>
                    @foreach($transactions as $tx)
                        @php $related = $tx->transaction_type === \App\Enums\FinancialTransactionType::Transfer ? $tx->relatedPerson : null; @endphp
                        <tr>
                            <td class="px-4 py-2 border-t whitespace-nowrap">{{ $tx->created_at->format('Y-m-d') }}</td>
                            <td class="px-4 py-2 border-t whitespace-nowrap">{{ $tx->transaction_type->label() }} <span class="text-xs text-gray-400">({{ $tx->direction === \App\Enums\FinancialDirection::MoneyIn ? 'وارد' : 'صادر' }})</span></td>
                            <td class="px-4 py-2 border-t text-gray-600 text-xs">
                                {{ $tx->description ?? '—' }}
                                @if($related)
                                    ← {{ $tx->direction === \App\Enums\FinancialDirection::MoneyOut ? 'إلى: ' : 'من: ' }}{{ $related->name }}
                                @endif
                            </td>
                            <td @class(['px-4 py-2 border-t font-bold text-left whitespace-nowrap', $tx->direction === \App\Enums\FinancialDirection::MoneyIn ? 'text-emerald-700' : 'text-red-700']) dir="ltr">
                                {{ $tx->direction === \App\Enums\FinancialDirection::MoneyIn ? '+' : '-' }}{{ number_format((float) $tx->amount, 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="bg-white rounded-xl shadow overflow-hidden mb-6">
    <div class="px-4 py-3 bg-amber-600 text-white font-bold flex items-center gap-2">
        <span>🏆</span> نقاط المكافآت
    </div>
    <div class="p-4 text-center">
        <div class="text-4xl font-bold text-amber-600">{{ $student->totalPoints() }}</div>
        <div class="text-sm text-gray-500 mt-1">إجمالي النقاط</div>
    </div>
    @if($student->rewardPoints()->exists())
        <div class="overflow-x-auto">
            <table class="w-full border-t">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-xs">
                        <th class="px-4 py-2 text-right whitespace-nowrap">النقاط</th>
                        <th class="px-4 py-2 text-right whitespace-nowrap">النوع</th>
                        <th class="px-4 py-2 text-right whitespace-nowrap">السبب</th>
                        <th class="px-4 py-2 text-right whitespace-nowrap">التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($student->rewardPoints()->with('awardedBy:id,name', 'quranReviewSession.surah:id,name_arabic')->latest()->limit(10)->get() as $rp)
                        <tr>
                            <td class="px-4 py-2 border-t font-bold whitespace-nowrap {{ $rp->type === 'earned' ? 'text-emerald-600' : 'text-red-600' }}">{{ $rp->type === 'earned' ? '+' : '-' }}{{ $rp->points }}</td>
                            <td class="px-4 py-2 border-t whitespace-nowrap"><span @class(['px-2 py-0.5 rounded-full text-xs font-bold', 'bg-emerald-100 text-emerald-800' => $rp->type === 'earned', 'bg-red-100 text-red-800' => $rp->type === 'deducted'])>{{ $rp->type === 'earned' ? 'ربح' : 'خصم' }}</span></td>
                            <td class="px-4 py-2 border-t text-sm text-gray-600">@if($rp->quranReviewSession)📖 تسميع {{ $rp->quranReviewSession->surah?->name_arabic }} @else {{ $rp->reason ?? '—' }} @endif</td>
                            <td class="px-4 py-2 border-t text-xs text-gray-500 whitespace-nowrap">{{ $rp->created_at->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="bg-white rounded-xl shadow overflow-hidden">
    <div class="px-4 py-3 bg-emerald-700 text-white font-bold">الدرجات</div>
    @if($student->grades->isEmpty())
        <div class="px-4 py-6 text-center text-gray-500">لا توجد درجات</div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 text-gray-600 text-sm">
                        <th class="px-4 py-3 text-right whitespace-nowrap">المادة</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الاختبار</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">التاريخ</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الدرجة</th>
                        <th class="px-4 py-3 text-right whitespace-nowrap">الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($student->grades as $grade)
                        <tr>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $grade->exam->subject?->name }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $grade->exam->title }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $grade->exam->exam_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">{{ $grade->score }} / {{ $grade->exam->total_marks }}</td>
                            <td class="px-4 py-3 border-t whitespace-nowrap">
                                @if($grade->status === 'draft') <span class="text-gray-600">مسودة</span>
                                @elseif($grade->status === 'submitted') <span class="text-yellow-600">بانتظار الاعتماد</span>
                                @elseif($grade->status === 'approved') <span class="text-green-600">معتمدة</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
