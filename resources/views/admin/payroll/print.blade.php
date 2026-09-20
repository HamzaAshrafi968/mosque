<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>كشوف رواتب {{ $monthInput }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Segoe UI", Tahoma, sans-serif; margin: 24px; color: #111827; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        .muted { color: #6b7280; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 5px 7px; text-align: right; }
        th { background: #f3f4f6; }
        .totals { margin-top: 16px; display: flex; flex-wrap: wrap; gap: 24px; font-size: 13px; }
        .totals b { font-size: 15px; }
        .sign { margin-top: 48px; display: flex; justify-content: space-between; font-size: 13px; }
        @media print { .no-print { display: none; } body { margin: 8mm; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:12px">
        <button onclick="window.print()" style="padding:8px 16px;background:#047857;color:#fff;border:0;border-radius:8px;cursor:pointer">طباعة / حفظ PDF</button>
    </div>

    <h1>كشوف رواتب المعلمين</h1>
    <div class="muted">
        {{ \App\Models\Tenant::find(config('app.current_tenant_id'))?->name }}
        — {{ \App\Support\QuranProgramSettings::monthLabel($monthInput) }}
    </div>

    <table>
        <thead>
            <tr>
                <th>المعلم</th>
                <th>الساعات</th>
                <th>سعر الساعة</th>
                <th>الإجمالي</th>
                <th>المدفوع</th>
                <th>المتبقي</th>
                <th>الحالة</th>
            </tr>
        </thead>
        <tbody>
        @foreach($teachers as $teacher)
            @php $summary = $summaries[$teacher->id] ?? null; @endphp
            @if($summary)
                <tr>
                    <td>{{ $teacher->name }}</td>
                    <td>{{ \App\Models\WorkSlot::formatMinutes($summary['total_minutes']) }}</td>
                    <td dir="ltr">
                        @if($summary['hourly_rate'] !== null)
                            {{ number_format((float) $summary['hourly_rate'], 2) }}
                        @elseif($summary['rate_is_mixed'])
                            متغيّر
                        @elseif($summary['current_rate'] !== null)
                            {{ number_format((float) $summary['current_rate'], 2) }}
                        @else
                            —
                        @endif
                    </td>
                    <td dir="ltr">{{ number_format($summary['gross'], 2) }}</td>
                    <td dir="ltr">{{ number_format($summary['paid'], 2) }}</td>
                    <td dir="ltr">{{ number_format($summary['remaining'], 2) }}</td>
                    <td>{{ $summary['status']->label() }} / {{ $summary['state']->label() }}</td>
                </tr>
            @endif
        @endforeach
        </tbody>
    </table>

    <div class="totals">
        <div>إجمالي الساعات: <b>{{ \App\Models\WorkSlot::formatMinutes($totals['minutes']) }}</b></div>
        <div>إجمالي الرواتب: <b dir="ltr">{{ number_format($totals['gross'], 2) }} {{ $currency }}</b></div>
        <div>المدفوع: <b dir="ltr">{{ number_format($totals['paid'], 2) }}</b></div>
        <div>المتبقي: <b dir="ltr">{{ number_format($totals['remaining'], 2) }}</b></div>
    </div>

    <div class="sign">
        <span>توقيع الإدارة: ..............................</span>
        <span>التاريخ: ..............................</span>
    </div>
</body>
</html>
