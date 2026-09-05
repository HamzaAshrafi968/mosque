<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'إدارة الجوامع') | {{ config('app.name', 'مسجد') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:ital,wght@0,400;0,700;1,400&family=Scheherazade+New:wght@400;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-gradient-to-br from-gray-50 via-emerald-50/30 to-teal-50/20 min-h-screen font-sans antialiased">
<x-super-admin-switcher />
<div class="min-h-screen lg:flex">
    @if(! auth()->user()->isSuperAdmin())
        <header class="lg:hidden sticky top-0 z-30 gradient-sidebar text-white flex items-center justify-between px-4 py-3 shadow-lg">
            <button type="button" id="sidebar-toggle" class="p-2 rounded-lg bg-white/10 hover:bg-white/20 transition" aria-label="القائمة">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <div class="flex items-center gap-2 font-bold">
                <span class="text-xl">🕌</span>
                <span class="text-sm">إدارة الجوامع</span>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="p-2 rounded-lg bg-white/10 hover:bg-white/20 transition text-xs" aria-label="تسجيل الخروج">
                    🚪
                </button>
            </form>
        </header>
    @endif

    <div id="sidebar-overlay" class="hidden fixed inset-0 bg-black/50 z-40 lg:hidden"></div>

    <aside id="sidebar" class="fixed inset-y-0 right-0 z-50 w-72 max-w-[85vw] gradient-sidebar text-white flex flex-col shrink-0 shadow-2xl overflow-hidden transition-transform duration-300 ease-in-out translate-x-full lg:translate-x-0 lg:static">
        <div class="absolute inset-0 opacity-5 pointer-events-none" style="background-image: repeating-linear-gradient(45deg, transparent, transparent 20px, rgba(255,255,255,0.15) 20px, rgba(255,255,255,0.15) 21px);"></div>

        <div class="relative p-5 border-b border-white/10 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-white/15 rounded-xl flex items-center justify-center text-2xl shadow-inner backdrop-blur-sm">
                    🕌
                </div>
                <div>
                    <div class="text-lg font-bold leading-tight">إدارة الجوامع</div>
                    <div class="text-xs text-emerald-300/80 mt-0.5">نظام إدارة المساجد</div>
                </div>
            </div>
            <button type="button" id="sidebar-close" class="lg:hidden p-2 rounded-lg bg-white/10 hover:bg-white/20 transition" aria-label="إغلاق القائمة">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <nav class="relative flex-1 p-3 space-y-0.5 overflow-y-auto">
            @php
                $user = auth()->user();
                $inMosqueContext = $user->isSuperAdmin() && session('super_admin_mosque_id');
                $unreadCount = $user->unreadNotifications()->count();
                $canSeeFinance = app(\App\Services\AuthorizationService::class)->canAny($user, ['finance.view', 'finance.create', 'finance.transfer']);
            @endphp
            @if($user->isAdmin() || $inMosqueContext)
                <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/></svg>
                    الرئيسية
                </x-nav-link>
                <x-nav-link :href="route('admin.students.index')" :active="request()->routeIs('admin.students.*')">الطلاب</x-nav-link>
                <x-nav-link :href="route('admin.teachers.index')" :active="request()->routeIs('admin.teachers.*')">المعلمون</x-nav-link>
                <x-nav-link :href="route('admin.custom-fields.index')" :active="request()->routeIs('admin.custom-fields.*')">الحقول المخصصة</x-nav-link>
                <x-nav-link :href="route('admin.classrooms.index')" :active="request()->routeIs('admin.classrooms.*')">الصفوف والشعب</x-nav-link>
                <x-nav-link :href="route('admin.subjects.index')" :active="request()->routeIs('admin.subjects.*')">المواد الدراسية</x-nav-link>
                <x-nav-link :href="route('admin.schedules.index')" :active="request()->routeIs('admin.schedules.*')">الجداول الدراسية</x-nav-link>
                <x-nav-link :href="route('admin.attendance.index')" :active="request()->routeIs('admin.attendance.*')">الحضور والغياب</x-nav-link>
                <x-nav-link :href="route('admin.exams.index')" :active="request()->routeIs('admin.exams.*')">الامتحانات</x-nav-link>
                <x-nav-link :href="route('admin.grades.index')" :active="request()->routeIs('admin.grades.*')">الدرجات</x-nav-link>
                <x-nav-link :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.*')">التقارير</x-nav-link>
                <x-nav-link :href="route('admin.announcements.index')" :active="request()->routeIs('admin.announcements.*')">الإعلانات</x-nav-link>
                <x-nav-link :href="route('admin.finance.index')" :active="request()->routeIs('admin.finance.*')">💰 العمليات المالية</x-nav-link>
                <x-nav-link :href="route('admin.audit-logs.index')" :active="request()->routeIs('admin.audit-logs.*')">📋 سجل العمليات</x-nav-link>
                <x-nav-link :href="route('admin.quran-review.index')" :active="request()->routeIs('admin.quran-review.*')">
                    📖 مراجعة القرآن
                </x-nav-link>
                <x-nav-link :href="route('admin.reward-points.index')" :active="request()->routeIs('admin.reward-points.*')">
                    🏆 نقاط المكافآت
                </x-nav-link>
                <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">الحسابات والصلاحيات</x-nav-link>
                <x-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                    🔔 الإشعارات
                    @if($unreadCount > 0)
                        <span class="mr-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5">{{ $unreadCount }}</span>
                    @endif
                </x-nav-link>
            @elseif($user->isSuperAdmin())
                <x-nav-link :href="route('super-admin.dashboard')" :active="request()->routeIs('super-admin.dashboard')">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/></svg>
                    لوحة التحكم
                </x-nav-link>
                <x-nav-link :href="route('super-admin.mosques.index')" :active="request()->routeIs('super-admin.mosques.*')">🕌 الجوامع</x-nav-link>
                <x-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                    🔔 الإشعارات
                    @if($unreadCount > 0)
                        <span class="mr-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5">{{ $unreadCount }}</span>
                    @endif
                </x-nav-link>
                <div class="pt-3 mt-3 border-t border-white/10 text-xs text-emerald-300/70 px-3">اختر جامعاً من القائمة العلوية لفتح لوحة إدارته الكاملة</div>
            @elseif($user->isGuardian())
                <x-nav-link :href="route('guardian.dashboard')" :active="request()->routeIs('guardian.dashboard')">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/></svg>
                    الرئيسية
                </x-nav-link>
                <x-nav-link :href="route('guardian.dashboard')" :active="request()->routeIs('guardian.children.*')">أبنائي</x-nav-link>
                <x-nav-link :href="route('guardian.profile')" :active="request()->routeIs('guardian.profile')">الملف الشخصي</x-nav-link>
                <x-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                    🔔 الإشعارات
                    @if($unreadCount > 0)
                        <span class="mr-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5">{{ $unreadCount }}</span>
                    @endif
                </x-nav-link>
                <div class="pt-3 mt-3 border-t border-white/10 text-xs text-emerald-300/70 px-3">يمكنك الاطلاع على بيانات أبنائك فقط</div>
            @elseif($user->isStudent())
                <x-nav-link :href="route('student.dashboard')" :active="request()->routeIs('student.dashboard')">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/></svg>
                    الرئيسية
                </x-nav-link>
                <x-nav-link :href="route('student.profile')" :active="request()->routeIs('student.profile')">ملفي الشخصي</x-nav-link>
                <x-nav-link :href="route('student.attendance')" :active="request()->routeIs('student.attendance')">الحضور والغياب</x-nav-link>
                <x-nav-link :href="route('student.subjects')" :active="request()->routeIs('student.subjects')">موادي الدراسية</x-nav-link>
                <x-nav-link :href="route('student.teachers')" :active="request()->routeIs('student.teachers')">معلموّي</x-nav-link>
                <x-nav-link :href="route('student.exams')" :active="request()->routeIs('student.exams')">الامتحانات</x-nav-link>
                <x-nav-link :href="route('student.grades')" :active="request()->routeIs('student.grades')">الدرجات</x-nav-link>
                <x-nav-link :href="route('student.homeworks')" :active="request()->routeIs('student.homeworks')">الواجبات</x-nav-link>
                <x-nav-link :href="route('student.announcements')" :active="request()->routeIs('student.announcements')">الإعلانات</x-nav-link>
                <x-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                    🔔 الإشعارات
                    @if($unreadCount > 0)
                        <span class="mr-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5">{{ $unreadCount }}</span>
                    @endif
                </x-nav-link>
            @else
                <x-nav-link :href="route('teacher.dashboard')" :active="request()->routeIs('teacher.dashboard')">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/></svg>
                    الرئيسية
                </x-nav-link>
                <x-nav-link :href="route('teacher.sections.index')" :active="request()->routeIs('teacher.sections.*')">شعبي والطلاب</x-nav-link>
                <x-nav-link :href="route('teacher.schedule')" :active="request()->routeIs('teacher.schedule')">جدولي الدراسي</x-nav-link>
                <x-nav-link :href="route('teacher.attendance.create')" :active="request()->routeIs('teacher.attendance.*')">تسجيل الحضور</x-nav-link>
                <x-nav-link :href="route('teacher.homeworks.index')" :active="request()->routeIs('teacher.homeworks.*') || request()->routeIs('teacher.submissions.*')">الواجبات</x-nav-link>
                <x-nav-link :href="route('teacher.exams.index')" :active="request()->routeIs('teacher.exams.*') && !request()->routeIs('teacher.grades.*')">الامتحانات</x-nav-link>
                <x-nav-link :href="route('teacher.lessons.index')" :active="request()->routeIs('teacher.lessons.*')">الدروس</x-nav-link>
                <x-nav-link :href="route('teacher.messages.index')" :active="request()->routeIs('teacher.messages.*')">الرسائل</x-nav-link>
                @if($canSeeFinance)
                    <x-nav-link :href="route('teacher.finance.index')" :active="request()->routeIs('teacher.finance.*')">💰 المالية</x-nav-link>
                @endif
                <x-nav-link :href="route('teacher.quran-review.index')" :active="request()->routeIs('teacher.quran-review.*')">
                    📖 مراجعة القرآن
                </x-nav-link>
                <x-nav-link :href="route('teacher.reward-points.index')" :active="request()->routeIs('teacher.reward-points.*')">
                    🏆 نقاط المكافآت
                </x-nav-link>
                <x-nav-link :href="route('teacher.profile.edit')" :active="request()->routeIs('teacher.profile.*')">الملف الشخصي</x-nav-link>
                <x-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                    🔔 الإشعارات
                    @if($unreadCount > 0)
                        <span class="mr-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5">{{ $unreadCount }}</span>
                    @endif
                </x-nav-link>
            @endif
        </nav>

        <div class="relative p-4 border-t border-white/10 bg-black/10">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-emerald-400/20 flex items-center justify-center text-emerald-200 font-bold text-sm shrink-0">
                    {{ mb_substr(auth()->user()->name, 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium truncate">{{ $user->name }}</div>
                    <div class="text-xs text-emerald-300/70">
                        @if($inMosqueContext)
                            داخل جامع (صلاحيات مدير الجامع)
                        @elseif($user->isAdmin())
                            مدير الجامع
                        @elseif($user->isSuperAdmin())
                            مدير الجوامع
                        @elseif($user->isGuardian())
                            ولي أمر
                        @elseif($user->isStudent())
                            طالب
                        @else
                            معلم
                        @endif
                    </div>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="w-full text-xs text-emerald-300/70 hover:text-white transition bg-white/5 hover:bg-white/10 rounded-lg py-1.5">
                    🚪 تسجيل الخروج
                </button>
            </form>
        </div>
    </aside>

    <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-x-hidden min-w-0">
        @if(session('success'))
            <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl px-5 py-4 flex items-center gap-3 animate-scale-in shadow-sm">
                <span class="text-xl">✅</span>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-6 bg-red-50 border border-red-200 text-red-800 rounded-2xl px-5 py-4 animate-scale-in shadow-sm">
                <div class="flex items-center gap-2 mb-2 font-bold">⚠️ يرجى تصحيح الأخطاء التالية:</div>
                <ul class="list-disc pr-5 space-y-1 text-sm">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
