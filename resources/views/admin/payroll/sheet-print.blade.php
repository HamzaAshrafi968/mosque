<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>كشف راتب {{ $teacher->name }} — {{ $monthInput }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 24px; color: #111827; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 15px; margin: 20px 0 6px; }
        .muted { color: #6b7280; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 5px 7px; text-align: right; }
        th { background: #f3f4f6; }
        .cards { display: flex; flex-wrap: wrap; gap: 18px; margin-top: 14px; font-size: 13px; }
        .cards b { font-size: 15px; }
        .sign { margin-top: 48px; display: flex; justify-content: space-between; font-size: 13px; }
        @media print { .no-print { display: none; } body { margin: 8mm; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:12px">
        <button onclick="window.print()" style="padding:8px 16px;background:#047857;color:#fff;border:0;border-radius:8px;cursor:pointer">طباعة / حفظ PDF</button>
    </div>

    <h1>كشف راتب — {{ $teacher->name }}</h1>
    <div class="muted">
        {{ $teacher->tenant?->name }}
        @if($teacher->studySessions->isNotEmpty())
            — {{ $teacher->studySessions->pluck('name')->implode('، ') }}
        @endif
        — {{ \App\Support\QuranProgramSettings::monthLabel($monthInput) }}
        — {{ $summary['pay_type']->label() }}
    </div>

    <div class="cards">
        <div>الساعات: <b>{{ \App\Models\WorkSlot::formatMinutes($summary['total_minutes']) }}</b></div>
        <div>الإجمالي: <b dir="ltr">{{ number_format($summary['gross'], 2) }} {{ $currency }}</b></div>
        <div>المدفوع: <b dir="ltr">{{ number_format($summary['paid'], 2) }}</b></div>
        <div>المتبقي: <b dir="ltr">{{ number_format($summary['remaining'], 2) }}</b></div>
        <div>الحالة: <b>{{ $summary['state']->label() }}</b></div>
    </div>

    @if($summary['breakdown'] !== [])
        <h2>تفصيل التسعير</h2>
        <table>
            <thead>
                <tr><th>السعر</th><th>الدقائق</th><th>من</th><th>إلى</th></tr>
            </thead>
            <tbody>
            @foreach($summary['breakdown'] as $segment)
                <tr>
                    <td dir="ltr">{{ number_format($segment['rate'], 2) }}</td>
                    <td>{{ \App\Models\WorkSlot::formatMinutes($segment['minutes']) }}</td>
                    <td dir="ltr">{{ $segment['from'] }}</td>
                    <td dir="ltr">{{ $segment['to'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @php $blocks = \App\Support\TimesheetAggregator::monthDayBlocks($month->year, $month->month); @endphp

    @if($slotsByDate->isNotEmpty())
        <h2>فترات العمل — أسابيع الشهر الأربعة</h2>
        @foreach($blocks as $block)
            @php
                $blockSlots = collect();
                $cursor = $block['start']->copy();
                while ($cursor->lte($block['end'])) {
                    $blockSlots = $blockSlots->merge($slotsByDate->get($cursor->toDateString(), collect()));
                    $cursor = $cursor->addDay();
                }
            @endphp
            <table>
                <thead>
                    <tr>
                        <th colspan="4" style="background:#e5e7eb">{{ $block['label'] }} — الإجمالي: {{ \App\Models\WorkSlot::formatMinutes((int) $blockSlots->sum(fn ($slot) => (int) $slot->duration_minutes)) }}</th>
                    </tr>
                    <tr><th>اليوم</th><th>التاريخ</th><th>من — إلى</th><th>الإجمالي</th></tr>
                </thead>
                <tbody>
                @php $day = $block['start']->copy(); @endphp
                @while($day->lte($block['end']))
                    @php $daySlots = $slotsByDate->get($day->toDateString(), collect()); @endphp
                    <tr>
                        <td>{{ \App\Enums\WorkDay::from($day->dayOfWeek)->label() }}</td>
                        <td dir="ltr">{{ $day->format('Y-m-d') }}</td>
                        <td dir="ltr">
                            @forelse($daySlots as $slot)
                                {{ substr($slot->start_time, 0, 5) }}—{{ substr($slot->end_time, 0, 5) }}@if(!$loop->last) ، @endif
                            @empty
                                —
                            @endforelse
                        </td>
                        <td>{{ $daySlots->isNotEmpty() ? \App\Models\WorkSlot::formatMinutes((int) $daySlots->sum(fn ($slot) => (int) $slot->duration_minutes)) : '—' }}</td>
                    </tr>
                    @php $day = $day->addDay(); @endphp
                @endwhile
                </tbody>
            </table>
        @endforeach
    @endif

    @if($payments->isNotEmpty())
        <h2>الدفعات</h2>
        <table>
            <thead>
                <tr><th>التاريخ</th><th>البيان</th><th>الطريقة</th><th>المبلغ</th><th>الحالة</th></tr>
            </thead>
            <tbody>
            @foreach($payments as $payment)
                <tr>
                    <td dir="ltr">{{ $payment->created_at->format('Y-m-d') }}</td>
                    <td>{{ $payment->description ?: '—' }}</td>
                    <td>{{ $payment->payment_method ?: '—' }}</td>
                    <td dir="ltr">{{ number_format((float) $payment->amount, 2) }}</td>
                    <td>{{ $payment->reversal ? 'معكوسة' : 'مُودعة' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <div class="sign">
        <span>توقيع الأستاذ: ..............................</span>
        <span>توقيع الإدارة: ..............................</span>
    </div>
</body>
</html>
