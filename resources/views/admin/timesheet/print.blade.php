<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>كشف عمل {{ $teacher->name }} — {{ $monthInput }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 24px; color: #111827; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #6b7280; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 13px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: right; }
        th { background: #f3f4f6; }
        .totals { margin-top: 16px; display: flex; gap: 24px; font-size: 13px; }
        .totals b { font-size: 15px; }
        .sign { margin-top: 48px; display: flex; justify-content: space-between; font-size: 13px; }
        @media print { .no-print { display: none; } body { margin: 8mm; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:12px">
        <button onclick="window.print()" style="padding:8px 16px;background:#047857;color:#fff;border:0;border-radius:8px;cursor:pointer">طباعة</button>
    </div>

    <h1>كشف عمل — {{ $teacher->name }}</h1>
    <div class="muted">
        {{ $teacher->tenant?->name }}
        @if($teacher->studySessions->isNotEmpty())
            — {{ $teacher->studySessions->pluck('name')->implode('، ') }}
        @endif
        — {{ \App\Support\QuranProgramSettings::monthLabel($monthInput) }}
    </div>

    @foreach($weeks as $week)
        @php
            $weekSlots = collect();
            $cursor = $week['start']->copy();
            while ($cursor->lte($week['end'])) {
                $weekSlots = $weekSlots->merge($slotsByDate->get($cursor->toDateString(), collect()));
                $cursor = $cursor->addDay();
            }
        @endphp

        @if($weekSlots->isNotEmpty())
            <table>
                <thead>
                    <tr><th colspan="4">{{ $week['label'] }} — {{ \App\Models\WorkSlot::formatMinutes((int) $weekSlots->sum(fn ($slot) => (int) $slot->duration_minutes)) }}</th></tr>
                    <tr>
                        <th style="width:120px">اليوم</th>
                        <th style="width:120px">التاريخ</th>
                        <th>الفترات</th>
                        <th style="width:90px">الإجمالي</th>
                    </tr>
                </thead>
                <tbody>
                @php $dayCursor = $week['start']->copy(); @endphp
                @while($dayCursor->lte($week['end']))
                    @php $daySlots = $slotsByDate->get($dayCursor->toDateString(), collect()); @endphp
                    @if($daySlots->isNotEmpty())
                        <tr>
                            <td>{{ \App\Enums\WorkDay::from($dayCursor->dayOfWeek)->label() }}</td>
                            <td dir="ltr">{{ $dayCursor->format('Y-m-d') }}</td>
                            <td dir="ltr">
                                @foreach($daySlots as $slot)
                                    {{ substr($slot->start_time, 0, 5) }}—{{ substr($slot->end_time, 0, 5) }}@if(!$loop->last) ، @endif
                                @endforeach
                            </td>
                            <td>{{ \App\Models\WorkSlot::formatMinutes((int) $daySlots->sum(fn ($slot) => (int) $slot->duration_minutes)) }}</td>
                        </tr>
                    @endif
                    @php $dayCursor = $dayCursor->addDay(); @endphp
                @endwhile
                </tbody>
            </table>
        @endif
    @endforeach

    <div class="totals">
        <div>إجمالي الفعلي: <b>{{ \App\Models\WorkSlot::formatMinutes($summary['total_minutes']) }}</b></div>
        <div>الإجمالي: <b dir="ltr">{{ number_format($summary['gross'], 2) }}</b></div>
        <div>المدفوع: <b dir="ltr">{{ number_format($summary['paid'], 2) }}</b></div>
        <div>المتبقي: <b dir="ltr">{{ number_format($summary['remaining'], 2) }}</b></div>
    </div>

    <div class="sign">
        <span>توقيع الأستاذ: ..............................</span>
        <span>توقيع الإدارة: ..............................</span>
    </div>
</body>
</html>
