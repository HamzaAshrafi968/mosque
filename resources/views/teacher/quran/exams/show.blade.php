@extends('layouts.app')

@section('title', 'اختبار حافظ - '.$exam->month)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <a href="{{ route('teacher.quran.exams.month', $exam->month) }}" class="text-sm text-emerald-700 hover:text-emerald-800">← اختبارات {{ $monthLabel($exam->month) }}</a>

    <div class="bg-gradient-to-l from-sky-700 to-emerald-700 text-white rounded-2xl p-6 shadow-lg flex flex-wrap items-center gap-4">
        <div class="flex-1 min-w-52">
            <div class="text-lg font-extrabold">{{ $exam->student->name }}</div>
            <div class="text-sm text-sky-100 mt-1">
                {{ $monthLabel($exam->month) }} · {{ $exam->student->classroom?->name ?? 'بدون صف' }}
                @if($exam->supervisor) · مشرف: {{ $exam->supervisor->name }} @endif
            </div>
        </div>
        <div class="text-center px-6">
            <div class="text-xs text-sky-200">النتيجة</div>
            <div class="text-2xl font-extrabold">{{ $exam->exam_status->label() }}</div>
        </div>
        <div class="text-center px-6">
            <div class="text-xs text-sky-200">الدرجة</div>
            <div class="text-2xl font-extrabold">{{ $exam->grade !== null ? $exam->grade.'/100' : '—' }}</div>
        </div>
    </div>

    <form method="POST" action="{{ route('teacher.quran.exams.grade', $exam) }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-4">
        @csrf
        <h3 class="font-extrabold text-gray-800">تسجيل نتيجة الشهر (النجاح من {{ $passMark }}/100)</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">الدرجة / 100 <span class="text-red-500">*</span></label>
                <input type="number" step="0.01" min="0" max="100" name="grade" required value="{{ old('grade', $exam->grade) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">تاريخ الاختبار</label>
                <input type="date" name="exam_date" value="{{ old('exam_date', $exam->exam_date?->format('Y-m-d') ?? now()->toDateString()) }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">ملاحظات</label>
                <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('notes', $exam->notes) }}</textarea>
            </div>
        </div>
        <p class="text-xs text-gray-400">يمكن إضافة الأجزاء المطلوب إعادتها بعد تسجيل النتيجة الراسبة من النموذج أسفل الصفحة.</p>
        <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">حفظ النتيجة</button>
    </form>

    @if($exam->revisions->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 border-b bg-gray-50 font-bold text-gray-800">🔄 الأجزاء المطلوب إعادتها</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="bg-gray-50 text-gray-600 text-xs">
                        <th class="px-4 py-2 text-right">الجزء/النطاق</th>
                        <th class="px-4 py-2 text-right">المقدار</th>
                        <th class="px-4 py-2 text-right">الحالة</th>
                        <th class="px-4 py-2 text-center">إجراء</th>
                    </tr></thead>
                    <tbody>
                    @foreach($exam->revisions as $revision)
                        <tr class="border-t">
                            <td class="px-4 py-2">
                                @if($revision->juz) جزء {{ $revision->juz }} @endif
                                @if($revision->from_surah) <div class="text-xs text-gray-500">من {{ $revision->fromSurah->name_arabic ?? '' }} {{ $revision->from_ayah ? 'آية '.$revision->from_ayah : '' }}</div> @endif
                                @if($revision->to_surah) <div class="text-xs text-gray-500">إلى {{ $revision->toSurah->name_arabic ?? '' }} {{ $revision->to_ayah ? 'آية '.$revision->to_ayah : '' }}</div> @endif
                            </td>
                            <td class="px-4 py-2">{{ $revision->amount ?? '—' }}</td>
                            <td class="px-4 py-2">
                                <span @class([
                                    'px-2 py-0.5 rounded-full text-xs font-bold',
                                    'bg-yellow-100 text-yellow-800' => $revision->status->value === 'pending',
                                    'bg-sky-100 text-sky-800' => $revision->status->value === 'completed',
                                    'bg-green-100 text-green-800' => $revision->status->value === 'approved',
                                ])>{{ $revision->status->label() }}</span>
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if($revision->status->value === 'pending')
                                    <form method="POST" action="{{ route('teacher.quran.exams.revisions.complete', $revision) }}">
                                        @csrf
                                        <button class="text-xs text-sky-700 hover:underline font-bold">تأكيد الإكمال</button>
                                    </form>
                                @elseif($revision->status->value === 'completed')
                                    <span class="text-xs text-gray-400">بانتظار اعتماد الإدارة</span>
                                @else
                                    <span class="text-xs text-green-700">معتمد ✔️</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($exam->exam_status->value === 'failed')
        <form method="POST" action="{{ route('teacher.quran.exams.revisions.store', $exam) }}" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-4">
            @csrf
            <h3 class="font-extrabold text-gray-800">+ إضافة جزء مطلوب إعادته</h3>
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3 items-end">
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">الجزء</label>
                    <input type="number" min="1" max="30" name="juz" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">من سورة</label>
                    <select name="from_surah" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">—</option>
                        @foreach($surahs as $surah)
                            <option value="{{ $surah->id }}">{{ $surah->name_arabic }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">من آية</label>
                    <input type="number" min="1" name="from_ayah" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">إلى سورة</label>
                    <select name="to_surah" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">—</option>
                        @foreach($surahs as $surah)
                            <option value="{{ $surah->id }}">{{ $surah->name_arabic }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">إلى آية</label>
                    <input type="number" min="1" name="to_ayah" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-600 mb-1">مقدار</label>
                    <input type="number" step="0.01" min="0" name="amount" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="col-span-2 md:col-span-5">
                    <label class="block text-xs font-bold text-gray-600 mb-1">ملاحظات</label>
                    <input type="text" name="notes" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white text-sm font-bold px-4 py-2 rounded-lg">إضافة</button>
            </div>
        </form>
    @endif
</div>
@endsection
