@extends('layouts.app')

@section('title', 'سجل العمليات')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">سجل العمليات (تدقيق)</h1>

<form method="GET" class="bg-white rounded-2xl shadow p-4 mb-6 flex flex-wrap gap-3 items-center">
    <input type="text" name="action" value="{{ request('action') }}" placeholder="بحث في الإجراء"
           class="flex-1 min-w-[160px] border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
    <select name="entity_type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        <option value="">كل الكيانات</option>
        @foreach($entityTypes as $type)
            <option value="{{ $type }}" @selected(request('entity_type') === $type)>{{ $type }}</option>
        @endforeach
    </select>
    <input type="date" name="date" value="{{ request('date') }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
    <button type="submit" class="bg-gray-800 text-white text-sm font-bold px-4 py-2 rounded-lg">تصفية</button>
</form>

<div class="bg-white rounded-2xl shadow overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-50 text-gray-500 text-right">
                    <th class="px-4 py-3 font-medium">التاريخ</th>
                    <th class="px-4 py-3 font-medium">المستخدم</th>
                    <th class="px-4 py-3 font-medium">الإجراء</th>
                    <th class="px-4 py-3 font-medium">الكيان</th>
                    <th class="px-4 py-3 font-medium">قبل</th>
                    <th class="px-4 py-3 font-medium">بعد</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr class="border-t border-gray-100 align-top">
                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                        <td class="px-4 py-3">{{ $log->user?->name ?? 'النظام' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded-lg text-xs font-bold bg-gray-100 text-gray-700" dir="ltr">{{ $log->action }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $log->entity_type }}
                            @if($log->entity_id)
                                <div class="text-xs text-gray-400 font-mono" dir="ltr">{{ substr($log->entity_id, 0, 8) }}…</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <pre class="text-[11px] text-gray-500 whitespace-pre-wrap font-mono max-w-xs">{{ $log->before ? json_encode($log->before, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '—' }}</pre>
                        </td>
                        <td class="px-4 py-3">
                            <pre class="text-[11px] text-gray-700 whitespace-pre-wrap font-mono max-w-xs">{{ $log->after ? json_encode($log->after, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : '—' }}</pre>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">لا توجد عمليات مسجلة</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-4">{{ $logs->links() }}</div>
</div>
@endsection
