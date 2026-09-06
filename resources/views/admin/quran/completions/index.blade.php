@extends('layouts.app')

@section('title', 'إتمام حفظ القرآن')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">إتمام حفظ القرآن</h2>
            <p class="text-sm text-gray-500 mt-1">عند تأكيد الإتمام يصبح الطالب حافظاً ويلتحق تلقائياً بالبرنامج التأهيلي + الاختبارات الشهرية</p>
        </div>
        <a href="{{ route('admin.quran.completions.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ تسجيل إتمام</a>
    </div>

    <div class="flex gap-2">
        @foreach(['pending' => '🎓 بانتظار التأكيد', 'confirmed' => '✔️ الحفاظ المؤكدون'] as $value => $label)
            <a href="{{ route('admin.quran.completions.index', ['status' => $value]) }}"
               @class([
                   'px-4 py-2 rounded-lg text-sm font-bold border',
                   'bg-emerald-700 text-white border-emerald-700' => $status === $value,
                   'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' => $status !== $value,
               ])>{{ $label }}</a>
        @endforeach
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right">الطالب</th>
                        <th class="px-4 py-3 text-right">تاريخ الإتمام</th>
                        @if($status === 'confirmed')
                            <th class="px-4 py-3 text-right">أُكد في</th>
                            <th class="px-4 py-3 text-right">أكد بواسطة</th>
                        @endif
                        <th class="px-4 py-3 text-right">ملاحظات</th>
                        <th class="px-4 py-3 text-center">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($completions as $completion)
                    <tr class="border-t">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('admin.quran.journey', $completion->student) }}" class="font-bold text-gray-800 hover:text-emerald-700">{{ $completion->student->name }}</a>
                            <div class="text-xs text-gray-400">{{ $completion->student->classroom?->name }}</div>
                        </td>
                        <td class="px-4 py-3">{{ $completion->completed_at?->format('Y-m-d') ?? '—' }}</td>
                        @if($status === 'confirmed')
                            <td class="px-4 py-3">{{ $completion->confirmed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $completion->confirmedBy?->name ?? '—' }}</td>
                        @endif
                        <td class="px-4 py-3 max-w-xs truncate">{{ $completion->notes ?? '—' }}</td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            @if(!$completion->isConfirmed())
                                <form method="POST" action="{{ route('admin.quran.completions.confirm', $completion) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-3 py-1.5 rounded-lg">تأكيد ← حافظ</button>
                                </form>
                            @else
                                <span class="text-xs text-emerald-700">حافظ ✔️</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">
                        @if($status === 'pending') لا توجد طلبات بانتظار التأكيد @else لا يوجد حفاظ بعد @endif
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">{{ $completions->links() }}</div>
    </div>
</div>
@endsection
