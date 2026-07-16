@extends('layouts.app')

@section('title', 'إحصائيات مراجعة القرآن')

@push('styles')
<style>
    .stat-hero {
        background: linear-gradient(135deg, #064e3b, #065f46, #047857);
        border-radius: 20px;
        padding: 28px;
        color: white;
        box-shadow: 0 4px 24px rgba(6, 78, 59, 0.15);
    }
    .mini-stat {
        background: white;
        border-radius: 14px;
        padding: 16px;
        text-align: center;
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }
    .mini-stat:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
    .rank-item {
        display: flex;
        align-items: center;
        gap: 14px;
        background: white;
        border-radius: 14px;
        padding: 14px 18px;
        border: 1px solid #f1f5f9;
        transition: all 0.2s ease;
    }
    .rank-item:hover { border-color: #10b981; background: #f0fdf4; }
    .rank-badge {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1rem;
    }
    .rank-1 { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
    .rank-2 { background: linear-gradient(135deg, #94a3b8, #64748b); color: white; }
    .rank-3 { background: linear-gradient(135deg, #d97706, #b45309); color: white; }
</style>
@endpush

@section('content')
<div class="space-y-6 max-w-6xl mx-auto">
    <a href="{{ route('admin.quran-review.index') }}" class="inline-flex items-center gap-1.5 text-emerald-600 hover:text-emerald-800 text-sm font-medium transition animate-fade-in-up">
        ← العودة إلى المراجعات
    </a>

    <div class="stat-hero animate-scale-in">
        <h2 class="text-2xl font-bold mb-6">📊 لوحة الإحصائيات</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-5 text-center border border-white/10">
                <div class="text-4xl font-extrabold mb-1">{{ $totalSessions }}</div>
                <div class="text-emerald-200 text-sm">إجمالي المراجعات</div>
            </div>
            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-5 text-center border border-white/10">
                <div class="text-4xl font-extrabold mb-1">{{ $totalStudents }}</div>
                <div class="text-emerald-200 text-sm">عدد الطلاب المراجَعين</div>
            </div>
            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-5 text-center border border-white/10">
                <div class="text-4xl font-extrabold mb-1">{{ $avgMastery }}%</div>
                <div class="text-emerald-200 text-sm">متوسط الإتقان العام</div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden animate-fade-in-up">
        <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
            <h3 class="text-lg font-bold text-gray-800">📉 إحصائيات الأخطاء حسب النوع</h3>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div class="mini-stat bg-red-50/60 border-red-100">
                    <div class="text-3xl font-extrabold text-red-600">{{ $errorTotals['incorrect'] }}</div>
                    <div class="text-xs text-red-500 mt-1 font-medium">أخطاء النطق</div>
                </div>
                <div class="mini-stat bg-yellow-50/60 border-yellow-100">
                    <div class="text-3xl font-extrabold text-yellow-600">{{ $errorTotals['hesitation'] }}</div>
                    <div class="text-xs text-yellow-500 mt-1 font-medium">تردد</div>
                </div>
                <div class="mini-stat bg-blue-50/60 border-blue-100">
                    <div class="text-3xl font-extrabold text-blue-600">{{ $errorTotals['tajweed_error'] }}</div>
                    <div class="text-xs text-blue-500 mt-1 font-medium">أخطاء التجويد</div>
                </div>
                <div class="mini-stat bg-pink-50/60 border-pink-100">
                    <div class="text-3xl font-extrabold text-pink-600">{{ $errorTotals['added'] }}</div>
                    <div class="text-xs text-pink-500 mt-1 font-medium">زيادة</div>
                </div>
                <div class="mini-stat bg-orange-50/60 border-orange-100">
                    <div class="text-3xl font-extrabold text-orange-600">{{ $errorTotals['forgotten'] }}</div>
                    <div class="text-xs text-orange-500 mt-1 font-medium">نسيان</div>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden animate-fade-in-up">
        <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
            <h3 class="text-lg font-bold text-gray-800">🏅 ترتيب الطلاب حسب الإتقان</h3>
        </div>
        <div class="p-6">
            @if($studentRankings->isEmpty())
                <p class="text-gray-400 text-center py-12 text-lg">لا توجد بيانات كافية بعد</p>
            @else
                <div class="space-y-2">
                    @foreach($studentRankings as $index => $student)
                        <div class="rank-item">
                            <span class="rank-badge {{ $index === 0 ? 'rank-1' : ($index === 1 ? 'rank-2' : ($index === 2 ? 'rank-3' : 'bg-gray-100 text-gray-500')) }}">
                                {{ $index + 1 }}
                            </span>
                            <div class="flex-1 font-semibold text-gray-800">{{ $student->name }}</div>
                            <div class="text-xs text-gray-400 bg-gray-100 rounded-full px-3 py-1">{{ $student->session_count }} مراجعة</div>
                            <div class="flex items-center gap-2 min-w-[120px]">
                                <div class="w-24 h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full {{ $student->avg_mastery >= 90 ? 'bg-emerald-500' : ($student->avg_mastery >= 70 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ $student->avg_mastery }}%"></div>
                                </div>
                                <span class="font-bold text-sm {{ $student->avg_mastery >= 90 ? 'text-emerald-600' : ($student->avg_mastery >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                    {{ round($student->avg_mastery, 1) }}%
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden animate-fade-in-up">
        <div class="px-6 py-4 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white">
            <h3 class="text-lg font-bold text-gray-800">📖 السور الأكثر مراجعة</h3>
        </div>
        <div class="p-6">
            @if($topSurahs->isEmpty())
                <p class="text-gray-400 text-center py-12 text-lg">لا توجد بيانات كافية بعد</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50/80">
                            <tr>
                                <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase">السورة</th>
                                <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase">عدد المراجعات</th>
                                <th class="px-5 py-3 text-right text-xs font-bold text-gray-500 uppercase">متوسط الإتقان</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($topSurahs as $s)
                                <tr class="hover:bg-emerald-50/20 transition">
                                    <td class="px-5 py-3.5">
                                        <span class="px-3 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-medium">{{ $s->name_arabic }}</span>
                                    </td>
                                    <td class="px-5 py-3.5 text-gray-600 font-medium">{{ $s->session_count }}</td>
                                    <td class="px-5 py-3.5">
                                        <div class="flex items-center gap-2">
                                            <div class="w-20 h-2 bg-gray-200 rounded-full overflow-hidden">
                                                <div class="h-full rounded-full {{ $s->avg_mastery >= 90 ? 'bg-emerald-500' : ($s->avg_mastery >= 70 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width: {{ $s->avg_mastery }}%"></div>
                                            </div>
                                            <span class="font-bold text-xs {{ $s->avg_mastery >= 90 ? 'text-emerald-600' : ($s->avg_mastery >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                                {{ round($s->avg_mastery, 1) }}%
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
