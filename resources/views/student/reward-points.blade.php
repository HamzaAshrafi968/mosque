@extends('layouts.app')

@section('title', 'نقاطي')

@section('content')
<div class="space-y-6 max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800">نقاطي 🏆</h1>

    <div class="grid grid-cols-3 gap-4">
        <div class="bg-gradient-to-br from-amber-500 to-orange-500 text-white rounded-2xl shadow p-5">
            <div class="text-sm opacity-90 mb-1">رصيد النقاط</div>
            <div class="text-3xl font-bold">{{ $balance }}</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <div class="text-sm text-gray-500 mb-1">✅ إجمالي المربوح</div>
            <div class="text-2xl font-bold text-emerald-600">{{ $totalEarned }}</div>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-5">
            <div class="text-sm text-gray-500 mb-1">❌ إجمالي المخصوم</div>
            <div class="text-2xl font-bold text-red-600">{{ $totalDeducted }}</div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="px-5 py-4 bg-gradient-to-r from-amber-600 to-orange-600 text-white font-bold">
            سجل النقاط
        </div>

        @if($points->isEmpty())
            <div class="p-12 text-center text-gray-400">
                <div class="text-4xl mb-3">🏆</div>
                لا توجد نقاط بعد — واصل الحفظ والمراجعة لتكسب النقاط
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 text-right">
                            <th class="px-4 py-3 font-medium">النقاط</th>
                            <th class="px-4 py-3 font-medium">السبب</th>
                            <th class="px-4 py-3 font-medium">الدوام</th>
                            <th class="px-4 py-3 font-medium">التاريخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($points as $point)
                            <tr class="border-t border-gray-100">
                                <td class="px-4 py-3">
                                    <span class="font-bold {{ $point->type === 'earned' ? 'text-emerald-600' : 'text-red-600' }}">
                                        {{ $point->type === 'earned' ? '+' : '-' }}{{ $point->points }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $point->reason ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ $point->studySession?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500 text-xs">{{ $point->created_at->format('Y-m-d') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-100">
                {{ $points->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
