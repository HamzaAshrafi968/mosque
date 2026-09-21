@extends('layouts.app')

@section('title', 'نقاط المكافآت')

@section('content')
@php
    $authorization = app(\App\Services\AuthorizationService::class);
    $can = fn (string $permission) => $authorization->can(auth()->user(), $permission);
@endphp
<div class="space-y-6 max-w-6xl mx-auto animate-fade-in-up">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <p class="text-gray-500 text-sm">🏆 سجل نقاط المكافآت للطلاب</p>
        </div>
        @if ($can('reward_points.create'))
            <a href="{{ route('admin.reward-points.create') }}" class="bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-3 rounded-xl hover:from-amber-600 hover:to-orange-600 transition shadow-lg shadow-amber-500/20 font-bold text-sm inline-flex items-center gap-2">
                <span class="text-lg">✚</span> إضافة نقاط (ربح / خسارة)
            </a>
        @endif
    </div>

    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-sm text-gray-500 mb-1">✅ إجمالي النقاط المربوحة</div>
            <div class="text-3xl font-bold text-emerald-600">{{ $totalEarned }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="text-sm text-gray-500 mb-1">❌ إجمالي النقاط المخصومة</div>
            <div class="text-3xl font-bold text-red-600">{{ $totalDeducted }}</div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden animate-scale-in">
        <div class="px-5 py-4 bg-gradient-to-r from-amber-600 to-orange-600 text-white font-bold text-lg flex items-center gap-2">
            <span>🏆</span> سجل نقاط المكافآت
        </div>

        <form method="GET" class="px-5 py-4 border-b border-gray-100 grid grid-cols-1 sm:grid-cols-4 gap-3">
            <select name="student_id" class="rounded-lg border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500">
                <option value="">كل الطلاب</option>
                @foreach($students as $student)
                    <option value="{{ $student->id }}" @selected(request('student_id') === $student->id)>{{ $student->name }}</option>
                @endforeach
            </select>
            <select name="study_session_id" class="rounded-lg border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500">
                <option value="">كل الدوامات</option>
                @foreach($sessions as $session)
                    <option value="{{ $session->id }}" @selected(request('study_session_id') === $session->id)>{{ $session->display_name }}</option>
                @endforeach
            </select>
            <select name="type" class="rounded-lg border-gray-300 text-sm focus:border-amber-500 focus:ring-amber-500">
                <option value="">كل الأنواع</option>
                <option value="earned" @selected(request('type') === 'earned')>ربح</option>
                <option value="deducted" @selected(request('type') === 'deducted')>خصم</option>
            </select>
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold px-4 py-2 rounded-lg">تصفية</button>
        </form>

        @if($points->isEmpty())
            <div class="p-16 text-center">
                <div class="w-20 h-20 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto mb-5">
                    <span class="text-5xl">🏆</span>
                </div>
                <p class="text-xl font-bold text-gray-600 mb-2">لا توجد نقاط بعد</p>
                <p class="text-gray-400 mb-6">ابدأ بإضافة نقاط (ربح) أو خصم نقاط (خسارة) للطلاب</p>
                @if ($can('reward_points.create'))
                    <a href="{{ route('admin.reward-points.create') }}" class="inline-flex items-center gap-2 bg-amber-500 text-white px-6 py-2.5 rounded-xl hover:bg-amber-600 transition font-medium">
                        ✨ إضافة نقاط
                    </a>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gradient-to-r from-gray-50 to-amber-50/50 border-b border-gray-200">
                            <th class="px-5 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">👨‍🎓 الطالب</th>
                            <th class="px-5 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">🌅 الدوام</th>
                            <th class="px-5 py-4 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">👤 أضافها</th>
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
                                        <a href="{{ route('admin.students.show', $point->student_id) }}" class="font-semibold text-gray-800 hover:text-amber-600 transition">
                                            {{ $point->student->name }}
                                        </a>
                                    </div>
                                </td>
                                <td class="px-5 py-4 text-gray-600">
                                    @if($point->studySession)
                                        <span class="text-xs font-semibold">{{ $point->studySession->display_name }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-gray-600">{{ $point->awardedBy?->name ?? 'النظام' }}</td>
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
                                        <span class="text-xs">🎧 استماع {{ $point->quranReviewSession->surah?->name_arabic }} ({{ $point->quranReviewSession->from_ayah }}-{{ $point->quranReviewSession->to_ayah }})</span>
                                    @else
                                        <span>{{ $point->reason ?? '—' }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-gray-500 text-xs">{{ $point->created_at->format('Y-m-d') }}</td>
                                <td class="px-5 py-4 text-center">
                                    @if(! $point->isAutomatic() && $can('reward_points.delete'))
                                        <form method="POST" action="{{ route('admin.reward-points.destroy', $point->id) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذه النقاط؟')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-3 py-1.5 bg-red-50 text-red-700 hover:bg-red-100 rounded-lg text-xs font-medium transition">
                                                🗑️ حذف
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-[11px] text-gray-400">تلقائي</span>
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
