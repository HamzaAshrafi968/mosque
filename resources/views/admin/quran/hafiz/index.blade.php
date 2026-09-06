@extends('layouts.app')

@section('title', 'الحفاظ')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-extrabold text-gray-800">📿 ملفات الحفاظ</h2>
            <p class="text-sm text-gray-500 mt-1">الحافظ هو الطالب الذي أُكد إتمام حفظه — نفس السجل الشخصي بلا تكرار</p>
        </div>
        <a href="{{ route('admin.quran.index') }}" class="text-sm text-emerald-700 hover:underline">← البرامج القرآنية</a>
    </div>

    <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 flex gap-3 items-end">
        <div class="flex-1">
            <label class="block text-xs font-bold text-gray-600 mb-1">بحث بالاسم</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="اسم الحافظ..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <button class="bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-bold px-5 py-2 rounded-lg">بحث</button>
    </form>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-gray-600">
                        <th class="px-4 py-3 text-right">الحافظ</th>
                        <th class="px-4 py-3 text-right">الصف</th>
                        <th class="px-4 py-3 text-right">مجاز</th>
                        <th class="px-4 py-3 text-right">الرواية</th>
                        <th class="px-4 py-3 text-right">إجازة جزرية</th>
                        <th class="px-4 py-3 text-center">إجراء</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($profiles as $profile)
                    <tr class="border-t">
                        <td class="px-4 py-3 whitespace-nowrap font-bold text-gray-800">{{ $profile->student->name }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $profile->student->classroom?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $profile->mujaz ? 'نعم ✔️' : 'لا' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $profile->riwayah ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $profile->ijazah_jazariyyah ? 'نعم ✔️' : 'لا' }}</td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <div class="flex gap-2 justify-center">
                                <a href="{{ route('admin.quran.hafiz.profile', $profile->student) }}" class="text-xs text-emerald-700 hover:underline">الملف</a>
                                <a href="{{ route('admin.quran.journey', $profile->student) }}" class="text-xs text-sky-700 hover:underline">الرحلة</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">لا يوجد حفاظ بعد — أُكّد إتمام الحفظ من صفحة «إتمام الحفظ»</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">{{ $profiles->links() }}</div>
    </div>
</div>
@endsection
