@extends('layouts.app')

@section('title', 'سجل نقاط المكافآت')

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 animate-fade-in-up">
        <div>
            <p class="text-gray-500 text-sm">🏆 سجل نقاط المكافآت للطلاب</p>
        </div>
        <a href="{{ route('teacher.reward-points.create') }}" class="bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-3 rounded-xl hover:from-amber-600 hover:to-orange-600 transition shadow-lg shadow-amber-500/20 font-bold text-sm inline-flex items-center gap-2">
            <span class="text-lg">✚</span> إضافة نقاط
        </a>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden animate-scale-in">
        @if($points->isEmpty())
            <div class="p-16 text-center">
                <div class="w-20 h-20 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <span class="text-5xl">🏆</span>
                </div>
                <p class="text-xl font-bold text-gray-600 mb-2">لا توجد نقاط بعد</p>
                <p class="text-gray-400 mb-6">ابدأ بإضافة نقاط للطلاب الآن</p>
                <a href="{{ route('teacher.reward-points.create') }}" class="inline-flex items-center gap-2 bg-amber-500 text-white px-6 py-2.5 rounded-xl hover:bg-amber-600 transition font-medium">
                    ✨ إضافة نقاط
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gradient-to-r from-gray-50 to-amber-50/50 border-b border-gray-200">
                            <th class="px-5 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">👨‍🎓 الطالب</th>
                            <th class="px-5 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">🏆 النقاط</th>
                            <th class="px-5 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">📋 النوع</th>
                            <th class="px-5 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">📝 السبب</th>
                            <th class="px-5 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">📅 التاريخ</th>
                            <th class="px-5 py-4 text-center text-xs font-bold text-gray-500 uppercase tracking-wider">⚙️ إجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($points as $point)
                            <tr class="hover:bg-amber-50/30 transition-all duration-150">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-8 h-8 rounded-full bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-xs">
                                            {{ mb_substr($point->student->name, 0, 1) }}
                                        </div>
                                        <span class="font-semibold text-gray-800">{{ $point->student->name }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="font-bold text-lg {{ $point->type === 'earned' ? 'text-emerald-600' : 'text-red-600' }}">
                                        {{ $point->type === 'earned' ? '+' : '-' }}{{ $point->points }}
                                    </span>
                                </td>
                                <td class="px-5 py-4">
                                    <span @class([
                                        'px-2 py-1 rounded-full text-xs font-bold',
                                        'bg-emerald-100 text-emerald-800' => $point->type === 'earned',
                                        'bg-red-100 text-red-800' => $point->type === 'deducted',
                                    ])>
                                        {{ $point->type === 'earned' ? 'ربح ✅' : 'خصم ❌' }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-gray-600">
                                    @if($point->quranReviewSession)
                                        <span class="text-xs">📖 تسميع {{ $point->quranReviewSession->surah?->name_arabic }} ({{ $point->quranReviewSession->from_ayah }}-{{ $point->quranReviewSession->to_ayah }})</span>
                                    @else
                                        <span>{{ $point->reason ?? '—' }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-gray-500 text-xs">{{ $point->created_at->format('Y-m-d') }}</td>
                                <td class="px-5 py-4 text-center">
                                    @if(!$point->quranReviewSession)
                                        <form method="POST" action="{{ route('teacher.reward-points.destroy', $point->id) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذه النقاط؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-3 py-1.5 bg-red-50 text-red-700 hover:bg-red-100 rounded-lg text-xs font-medium transition">
                                                🗑️ حذف
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-5 border-t border-gray-100">
                {{ $points->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
