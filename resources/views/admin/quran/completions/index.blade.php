@extends('layouts.app')

@section('title', 'إتمام الحفظ والحفاظ')

@section('content')
@php($canViewCompletions = $can('quran.completion.view'))
@php($canViewHafiz = $can('hafiz_profile.view'))
@php($canConfirm = $can('quran.completion.confirm'))
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">🎓 إتمام الحفظ والحفاظ</h2>
            <p class="text-sm text-gray-500 mt-1">عند تأكيد الإتمام يصبح الطالب حافظاً ويلتحق تلقائياً بالبرنامج التأهيلي + الاختبارات الشهرية</p>
        </div>
        @if($canViewCompletions)
            <a href="{{ route('admin.quran.completions.create') }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-4 py-2 rounded-lg">+ إضافة طالب حافظ</a>
        @endif
    </div>

    <div class="flex flex-wrap gap-2">
        @if($canViewCompletions)
            <a href="{{ route('admin.quran.completions.index', ['status' => 'pending']) }}"
               @class([
                   'inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold border',
                   'bg-emerald-700 text-white border-emerald-700' => $status === 'pending',
                   'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' => $status !== 'pending',
               ])>
                🎓 بانتظار التأكيد
                <span @class([
                    'min-w-6 text-center rounded-full px-1.5 py-0.5 text-xs font-extrabold',
                    'bg-white/20 text-white' => $status === 'pending',
                    'bg-amber-100 text-amber-800' => $status !== 'pending',
                ])>{{ $pendingCount }}</span>
            </a>
        @endif
        @if($canViewHafiz || $canViewCompletions)
            <a href="{{ route('admin.quran.completions.index', ['status' => 'confirmed']) }}"
               @class([
                   'inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold border',
                   'bg-emerald-700 text-white border-emerald-700' => $status === 'confirmed',
                   'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' => $status !== 'confirmed',
               ])>
                📿 الحفاظ المؤكدون
                <span @class([
                    'min-w-6 text-center rounded-full px-1.5 py-0.5 text-xs font-extrabold',
                    'bg-white/20 text-white' => $status === 'confirmed',
                    'bg-emerald-100 text-emerald-800' => $status !== 'confirmed',
                ])>{{ $hafizCount }}</span>
            </a>
        @endif
    </div>

    @if($status === 'confirmed')
        <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 flex flex-wrap gap-3 items-end">
            <input type="hidden" name="status" value="confirmed">
            <div class="flex-1 min-w-52">
                <label class="block text-xs font-bold text-gray-600 mb-1">بحث بالاسم</label>
                <input type="text" name="q" value="{{ $search }}" placeholder="اسم الحافظ..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2 rounded-lg">بحث</button>
            @if($search)
                <a href="{{ route('admin.quran.completions.index', ['status' => 'confirmed']) }}" class="text-sm text-gray-500 hover:underline px-2 py-2">مسح</a>
            @endif
        </form>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600">
                            <th class="px-4 py-3 text-right">الحافظ</th>
                            <th class="px-4 py-3 text-right">تاريخ الإتمام</th>
                            @if($canViewHafiz)
                                <th class="px-4 py-3 text-right">الإجازة والقراءات</th>
                            @endif
                            <th class="px-4 py-3 text-right">أُكد بواسطة</th>
                            <th class="px-4 py-3 text-center">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($profiles as $profile)
                        @php($completion = $profile->student->latestConfirmedCompletion)
                        @php($qualifyingEnrollment = $qualifyingEnrollments->get($profile->student_id))
                        <tr class="border-t">
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($canViewHafiz)
                                    <a href="{{ route('admin.quran.hafiz.profile', $profile->student) }}" class="font-bold text-gray-800 hover:text-emerald-700">{{ $profile->student->name }}</a>
                                @else
                                    <span class="font-bold text-gray-800">{{ $profile->student->name }}</span>
                                @endif
                                <div class="text-xs text-gray-400">{{ $profile->student->classroom?->name ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $completion?->completed_at?->format('Y-m-d') ?? '—' }}</td>
                            @if($canViewHafiz)
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-1">
                                        @if($profile->mujaz)
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800">مجاز ✔️</span>
                                        @endif
                                        @if($profile->ijazah_jazariyyah)
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-sky-100 text-sky-800">جزرية ✔️</span>
                                        @endif
                                        @if($profile->riwayah)
                                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-700">{{ $profile->riwayah }}</span>
                                        @endif
                                        @if(!$profile->mujaz && !$profile->ijazah_jazariyyah && !$profile->riwayah)
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </div>
                                </td>
                            @endif
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-gray-700">{{ $completion?->confirmedBy?->name ?? '—' }}</div>
                                <div class="text-xs text-gray-400">{{ $completion?->confirmed_at?->format('Y-m-d') }}</div>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <div class="flex flex-wrap gap-2 justify-center items-center">
                                    @if($qualifyingEnrollment)
                                        <span @class([
                                            'px-2 py-0.5 rounded-full text-[11px] font-bold',
                                            'bg-emerald-100 text-emerald-800' => $qualifyingEnrollment->status === \App\Enums\ProgramEnrollmentStatus::Active,
                                            'bg-gray-100 text-gray-600' => $qualifyingEnrollment->status !== \App\Enums\ProgramEnrollmentStatus::Active,
                                        ])>
                                            {{ $qualifyingEnrollment->status === \App\Enums\ProgramEnrollmentStatus::Active ? 'في التأهيلي' : 'أكمل التأهيلي' }}
                                        </span>
                                    @elseif($can('qualifying.create'))
                                        <form method="POST" action="{{ route('admin.quran.hafiz.qualifying', $profile->student) }}"
                                              onsubmit="return confirm('سيُلتحق الحافظ بالبرنامج التأهيلي. متأكد؟')">
                                            @csrf
                                            <button type="submit" class="text-xs text-amber-700 hover:underline">ترحيل للتأهيلي</button>
                                        </form>
                                    @endif
                                    @if($canViewHafiz)
                                        <a href="{{ route('admin.quran.hafiz.profile', $profile->student) }}" class="text-xs text-emerald-700 hover:underline">الملف</a>
                                    @endif
                                    @if($can('quran.tasmee.view'))
                                        <a href="{{ route('admin.quran.journey', $profile->student) }}" class="text-xs text-sky-700 hover:underline">الرحلة</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $canViewHafiz ? 5 : 4 }}" class="px-4 py-8 text-center text-gray-400">
                            @if($search)
                                لا يوجد حافظ بهذا الاسم
                            @else
                                لا يوجد حفاظ بعد — أضف طالباً حافظاً أو أكّد طلب إتمام من تبويب «بانتظار التأكيد»
                            @endif
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-100">{{ $profiles->links() }}</div>
        </div>
    @else
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600">
                            <th class="px-4 py-3 text-right">الطالب</th>
                            <th class="px-4 py-3 text-right">تاريخ الإتمام</th>
                            <th class="px-4 py-3 text-right">ملاحظات</th>
                            <th class="px-4 py-3 text-center">إجراء</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($completions as $completion)
                        <tr class="border-t">
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($can('quran.tasmee.view'))
                                    <a href="{{ route('admin.quran.journey', $completion->student) }}" class="font-bold text-gray-800 hover:text-emerald-700">{{ $completion->student->name }}</a>
                                @else
                                    <span class="font-bold text-gray-800">{{ $completion->student->name }}</span>
                                @endif
                                <div class="text-xs text-gray-400">{{ $completion->student->classroom?->name }}</div>
                            </td>
                            <td class="px-4 py-3">{{ $completion->completed_at?->format('Y-m-d') ?? '—' }}</td>
                            <td class="px-4 py-3 max-w-xs truncate">{{ $completion->notes ?? '—' }}</td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if($canConfirm)
                                    <form method="POST" action="{{ route('admin.quran.completions.confirm', $completion) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-3 py-1.5 rounded-lg">تأكيد ← حافظ</button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-400">بانتظار التأكيد</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">لا توجد طلبات بانتظار التأكيد</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-gray-100">{{ $completions->links() }}</div>
        </div>
    @endif
</div>
@endsection
