@extends('layouts.app')

@section('title', 'بوابة ولي الأمر')

@section('content')
<h1 class="text-2xl font-bold text-gray-800 mb-6">أبنائي</h1>

@forelse($cards as $card)
    @php $student = $card['student']; @endphp
    <a href="{{ route('guardian.children.overview', $student) }}" class="block bg-white rounded-2xl shadow hover:shadow-lg transition mb-4 overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-4 p-5">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl font-bold">
                    {{ mb_substr($student->name, 0, 1) }}
                </div>
                <div>
                    <div class="text-lg font-bold text-gray-800">{{ $student->name }}</div>
                    <div class="text-sm text-gray-500">
                        {{ $student->classroom?->name ?? 'غير مقيد' }}
                        @if($student->section)
                            — شعبة {{ $student->section->name }}
                        @endif
                    </div>
                </div>
            </div>
            <div class="text-center">
                <div class="text-2xl font-bold {{ ($card['attendance']['percentage'] ?? null) !== null && $card['attendance']['percentage'] < 75 ? 'text-red-500' : 'text-emerald-600' }}">
                    {{ $card['attendance']['percentage'] !== null ? $card['attendance']['percentage'].'٪' : '—' }}
                </div>
                <div class="text-xs text-gray-500">نسبة الحضور</div>
            </div>
            <div class="flex gap-6 text-sm text-gray-600">
                <div><span class="text-red-500 font-bold">{{ $card['attendance']['absent'] }}</span> غياب</div>
                <div><span class="text-amber-500 font-bold">{{ $card['attendance']['late'] }}</span> تأخر</div>
                <div><span class="text-blue-600 font-bold">{{ count($card['upcomingExams']) }}</span> امتحانات قادمة</div>
                <div><span class="text-emerald-600 font-bold">{{ $card['pendingHomeworks'] }}</span> واجبات بانتظار الإنجاز</div>
            </div>
        </div>
    </a>
@empty
    <div class="bg-white rounded-2xl shadow p-8 text-center text-gray-500">
        لا يوجد أبناء مرتبطون بحسابك حالياً
    </div>
@endforelse

<div class="mt-8 bg-white rounded-2xl shadow p-5 text-sm text-gray-500">
    💡 يمكنك النقر على أي طفل لفتح ملفه الأكاديمي الكامل (الحضور، المواد، المعلمون، الامتحانات، الدرجات، الواجبات، الإعلانات).
</div>
@endsection
