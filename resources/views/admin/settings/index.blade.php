@extends('layouts.app')

@section('title', 'مركز الإعدادات')

@section('content')
<div class="max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">مركز الإعدادات</h1>
    <p class="text-sm text-gray-500 mb-6">كل ما يخص ضبط الجامع في مكان واحد: برنامج القرآن، نقاط المكافآت، الصلاحيات، الدوامات والبرامج.</p>

    @include('admin.settings._tabs')

    <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-5">
        @if ($canPortalNotice)
            <a href="{{ route('admin.settings.portal-notice.edit') }}"
                class="card-hover block bg-white rounded-2xl shadow p-6 border border-transparent hover:border-emerald-200">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 grid place-items-center">
                        <x-icon name="megaphone" class="w-6 h-6" />
                    </span>
                    <span class="text-[11px] font-bold rounded-full px-3 py-1 {{ $portalNotice ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $portalNotice ? 'الإعلان مفعّل' : 'لا يوجد إعلان' }}
                    </span>
                </div>
                <h2 class="font-bold text-gray-800 mt-4">إعلان بوابة أولياء الأمور</h2>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">نص ثابت يظهر أعلى الصفحة الرئيسية لبوابة ولي الأمر.</p>
            </a>
        @endif

        @if ($canQuranSettings)
            <a href="{{ route('admin.settings.quran.edit') }}"
                class="card-hover block bg-white rounded-2xl shadow p-6 border border-transparent hover:border-emerald-200">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-700 grid place-items-center">
                        <x-icon name="quran" class="w-6 h-6" />
                    </span>
                    <span class="text-[11px] font-bold rounded-full bg-emerald-50 text-emerald-700 px-3 py-1">
                        {{ rtrim(rtrim(number_format($minimumPassingPercentage, 1), '0'), '.') }}% حد النجاح
                    </span>
                </div>
                <h2 class="font-bold text-gray-800 mt-4">إعدادات برنامج القرآن</h2>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">حد النجاح الموحّد لجميع اختبارات القرآن (دفعات الحفظ وبرامج الاستماع واختبار الشهر للحفاظ).</p>
            </a>

            <a href="{{ route('admin.settings.rewards.edit') }}"
                class="card-hover block bg-white rounded-2xl shadow p-6 border border-transparent hover:border-amber-200">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-700 grid place-items-center">
                        <x-icon name="trophy" class="w-6 h-6" />
                    </span>
                    <span class="text-[11px] font-bold rounded-full px-3 py-1 {{ $automaticEnabled ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $automaticEnabled ? 'المنح التلقائي مفعّل' : 'المنح التلقائي موقوف' }}
                    </span>
                </div>
                <h2 class="font-bold text-gray-800 mt-4">إعدادات نقاط المكافآت</h2>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                    نقاط الحفظ والخمسات واختبارات الدفعات وخطط الاستماع والدورات الشرعية —
                    <span class="font-bold text-gray-600">{{ $activeRules }}</span> قاعدة مفعّلة.
                </p>
            </a>
        @endif

        @if ($canWorkHours)
            <a href="{{ route('admin.settings.work-hours.edit') }}"
                class="card-hover block bg-white rounded-2xl shadow p-6 border border-transparent hover:border-gold-200">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-gold-50 text-gold-600 grid place-items-center">
                        <x-icon name="clock" class="w-6 h-6" />
                    </span>
                    <span class="text-[11px] font-bold rounded-full bg-gold-50 text-gold-700 px-3 py-1">
                        حد الفترة {{ rtrim(rtrim(number_format($maxSlotHours, 1), '0'), '.') }} ساعات
                    </span>
                </div>
                <h2 class="font-bold text-gray-800 mt-4">ساعات العمل والرواتب</h2>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">الحد الأقصى لفترة العمل، توقيت الجامع، وربط كشوف الرواتب بأسعار الساعة.</p>
            </a>
        @endif

        @if ($canHourlyRates)
            <a href="{{ route('admin.settings.hourly-rates.index') }}"
                class="card-hover block bg-white rounded-2xl shadow p-6 border border-transparent hover:border-rose-200">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-700 grid place-items-center">
                        <x-icon name="wallet" class="w-6 h-6" />
                    </span>
                    <span class="text-[11px] font-bold rounded-full bg-rose-50 text-rose-700 px-3 py-1">
                        {{ $activeRatesCount }} سعر ساري
                    </span>
                </div>
                <h2 class="font-bold text-gray-800 mt-4">أسعار الساعة</h2>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">سعر ساعة كل أستاذ — الراتب كله بالساعات: ساعات العمل × سعر الساعة.</p>
            </a>
        @endif

        @if ($canUsers)
            <a href="{{ route('admin.users.index') }}"
                class="card-hover block bg-white rounded-2xl shadow p-6 border border-transparent hover:border-sky-200">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-700 grid place-items-center">
                        <x-icon name="shield" class="w-6 h-6" />
                    </span>
                </div>
                <h2 class="font-bold text-gray-800 mt-4">الحسابات والصلاحيات</h2>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">إنشاء الحسابات، تحديد الأدوار، وضبط صلاحيات المعلمين ومديري الدوامات.</p>
            </a>
        @endif

        @if ($canSessions)
            <a href="{{ route('admin.sessions.index') }}"
                class="card-hover block bg-white rounded-2xl shadow p-6 border border-transparent hover:border-teal-200">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-teal-50 text-teal-700 grid place-items-center">
                        <x-icon name="clock" class="w-6 h-6" />
                    </span>
                </div>
                <h2 class="font-bold text-gray-800 mt-4">الدوامات</h2>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">دوامات الجامع (الأول/الثاني...) والبرامج المتاحة في كل دوام.</p>
            </a>
        @endif

        @if ($canPrograms)
            <a href="{{ route('admin.programs.index') }}"
                class="card-hover block bg-white rounded-2xl shadow p-6 border border-transparent hover:border-violet-200">
                <div class="flex items-start justify-between gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-violet-50 text-violet-700 grid place-items-center">
                        <x-icon name="fields" class="w-6 h-6" />
                    </span>
                </div>
                <h2 class="font-bold text-gray-800 mt-4">البرامج والتخصصات</h2>
                <p class="text-xs text-gray-500 mt-1 leading-relaxed">تخصصات الجداول الدراسية وفتراتها وخصائصها.</p>
            </a>
        @endif
    </div>

    <div class="mt-6 rounded-2xl bg-white/70 border border-gray-200 p-4 text-xs text-gray-500 leading-relaxed">
        إعدادات النقاط تُطبَّق على الدوام المختار فقط، وتعديل أي قاعدة لا يغيّر النقاط الممنوحة سابقاً.
    </div>
</div>
@endsection
