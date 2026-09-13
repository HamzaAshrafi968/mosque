<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'مؤسسة السفرة للعلوم والتنمية') | {{ config('app.name', 'مؤسسة السفرة للعلوم والتنمية') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=Amiri:ital,wght@0,400;0,700;1,400&family=Scheherazade+New:wght@400;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-[#f4f6f4] min-h-screen font-sans antialiased">
<x-super-admin-switcher />

<div aria-hidden="true" class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
    <div class="absolute -top-28 -start-28 w-[26rem] h-[26rem] rounded-full bg-emerald-300/20 blur-3xl"></div>
    <div class="absolute top-1/3 -end-32 w-[30rem] h-[30rem] rounded-full bg-gold-200/25 blur-3xl"></div>
    <div class="absolute -bottom-32 start-1/4 w-96 h-96 rounded-full bg-teal-200/20 blur-3xl"></div>
</div>

@if(auth()->user()->isAdmin())
    <header class="gradient-sidebar relative text-white shadow-[0_14px_34px_-16px_rgba(5,32,25,0.65)] sticky top-0 z-40">
        <div aria-hidden="true" class="topbar-sheen pointer-events-none absolute inset-0 opacity-20"></div>
        <div class="relative max-w-screen-2xl mx-auto px-3 sm:px-6 py-2.5 flex items-center gap-2.5 sm:gap-3">
            <button
                type="button"
                id="sidebar-toggle"
                class="lg:hidden p-2 -me-1 rounded-xl bg-white/10 hover:bg-white/20 active:scale-95 transition-all"
                aria-label="القائمة"
            >
                <x-icon name="menu" class="w-5 h-5" />
            </button>

            <div class="flex items-center gap-2.5 font-bold shrink-0 min-w-0">
                <span class="w-10 h-10 rounded-xl p-[1.5px] bg-gradient-to-br from-gold-200 via-gold-400 to-gold-600 shadow-lg shadow-gold-950/20 shrink-0">
                    <span class="w-full h-full rounded-[10px] bg-white grid place-items-center overflow-hidden">
                        <img src="{{ asset('images/logo-mark.png') }}" alt="شعار مؤسسة السفرة للعلوم والتنمية" class="w-7 h-7 object-contain">
                    </span>
                </span>
                <div class="leading-tight min-w-0">
                    <div class="text-sm sm:text-base font-extrabold truncate">جامع {{ auth()->user()->tenant?->name }}</div>
                    <div class="text-[10px] sm:text-[11px] text-gold-200/90 font-semibold truncate">لوحة إدارة الجامع — اختر الدوام لعرض بياناته</div>
                </div>
            </div>

            <div class="ms-auto flex items-center gap-2 min-w-0">
                <x-study-session-switcher />
            </div>
        </div>
        <div class="absolute inset-x-0 bottom-0 gold-hairline"></div>
    </header>
@endif

<div class="min-h-screen lg:flex">
    @if(! auth()->user()->isSuperAdmin() && ! auth()->user()->isAdmin())
        <header class="lg:hidden sticky top-0 z-30 gradient-sidebar relative text-white flex items-center justify-between px-4 py-3 shadow-lg overflow-hidden">
            <div aria-hidden="true" class="topbar-sheen pointer-events-none absolute inset-0 opacity-20"></div>
            <button type="button" id="sidebar-toggle" class="relative p-2 rounded-xl bg-white/10 hover:bg-white/20 active:scale-95 transition" aria-label="القائمة">
                <x-icon name="menu" class="w-6 h-6" />
            </button>
            <div class="relative flex items-center gap-2 font-bold">
                <span class="bg-white rounded-lg p-1 grid place-items-center overflow-hidden"><img src="{{ asset('images/logo-mark.png') }}" alt="شعار مؤسسة السفرة للعلوم والتنمية" class="w-6 h-6 object-contain"></span>
                <span class="text-sm">مؤسسة السفرة للعلوم والتنمية</span>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="relative">
                @csrf
                <button type="submit" class="p-2 rounded-xl bg-white/10 hover:bg-white/20 active:scale-95 transition" aria-label="تسجيل الخروج">
                    <x-icon name="logout" class="w-5 h-5" />
                </button>
            </form>
        </header>
    @endif

    <div id="sidebar-overlay" class="hidden fixed inset-0 bg-pine-950/60 backdrop-blur-[2px] z-40 lg:hidden"></div>

    <aside id="sidebar" class="gradient-sidebar fixed inset-y-0 right-0 z-50 w-72 max-w-[85vw] text-white flex flex-col shrink-0 overflow-hidden transition-transform duration-300 ease-out translate-x-full lg:translate-x-0 lg:static shadow-2xl shadow-pine-950/40">
        <div class="sidebar-pattern absolute inset-0 pointer-events-none"></div>
        <div aria-hidden="true" class="absolute -top-20 -start-20 w-64 h-64 rounded-full bg-gold-400/10 blur-3xl pointer-events-none"></div>

        <div class="relative p-5 border-b border-white/10 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-2xl p-[1.5px] bg-gradient-to-br from-gold-200 via-gold-400 to-gold-600 shadow-lg shadow-pine-950/30">
                    <div class="w-full h-full rounded-[13px] bg-white grid place-items-center overflow-hidden">
                        <img src="{{ asset('images/logo-mark.png') }}" alt="شعار مؤسسة السفرة للعلوم والتنمية" class="w-8 h-8 object-contain">
                    </div>
                </div>
                <div>
                    <div class="text-lg font-black leading-tight">مؤسسة السفرة للعلوم والتنمية</div>
                    <div class="text-[11px] text-gold-200/80 mt-0.5 font-semibold">نظام إدارة المساجد وحلقات القرآن</div>
                </div>
            </div>
            <button type="button" id="sidebar-close" class="lg:hidden p-2 rounded-xl bg-white/10 hover:bg-white/20 active:scale-95 transition" aria-label="إغلاق القائمة">
                <x-icon name="x" class="w-5 h-5" />
            </button>
        </div>

        <nav class="relative flex-1 p-3 space-y-1 overflow-y-auto">
            @php
                $user = auth()->user();
                $inMosqueContext = $user->isSuperAdmin() && session('super_admin_mosque_id');
                $unreadCount = $user->unreadNotifications()->count();
                $canSeeFinance = app(\App\Services\AuthorizationService::class)->canAny($user, ['finance.view', 'finance.create', 'finance.transfer']);
            @endphp
            @if($user->isAdmin() || $inMosqueContext)
                <x-nav-link icon="home" :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                    <span>الرئيسية</span>
                </x-nav-link>
                <x-nav-link icon="students" :href="route('admin.students.index')" :active="request()->routeIs('admin.students.*')"><span>الطلاب</span></x-nav-link>
                <x-nav-link icon="teachers" :href="route('admin.teachers.index')" :active="request()->routeIs('admin.teachers.*') && !request()->routeIs('admin.teachers.work-hours.*')"><span>المعلمون</span></x-nav-link>
                <x-nav-link icon="clock" :href="route('admin.work-hours.index')" :active="request()->routeIs('admin.work-hours.*') || request()->routeIs('admin.teachers.work-hours.*')"><span>ساعات العمل</span></x-nav-link>
                <x-nav-link icon="fields" :href="route('admin.custom-fields.index')" :active="request()->routeIs('admin.custom-fields.*')"><span>الحقول المخصصة</span></x-nav-link>
                <x-nav-link icon="classrooms" :href="route('admin.classrooms.index')" :active="request()->routeIs('admin.classrooms.*')"><span>الصفوف والشعب</span></x-nav-link>
                <x-nav-link icon="subjects" :href="route('admin.subjects.index')" :active="request()->routeIs('admin.subjects.*')"><span>المواد الدراسية</span></x-nav-link>
                <x-nav-link icon="calendar" :href="route('admin.schedules.index')" :active="request()->routeIs('admin.schedules.*')"><span>الجداول الدراسية</span></x-nav-link>
                <x-nav-link icon="attendance" :href="route('admin.attendance.index')" :active="request()->routeIs('admin.attendance.*')"><span>الحضور والغياب</span></x-nav-link>
                <x-nav-link icon="exam" :href="route('admin.exams.index')" :active="request()->routeIs('admin.exams.*')"><span>الامتحانات</span></x-nav-link>
                <x-nav-link icon="grades" :href="route('admin.grades.index')" :active="request()->routeIs('admin.grades.*')"><span>الدرجات</span></x-nav-link>
                <x-nav-link icon="reports" :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.*')"><span>التقارير</span></x-nav-link>
                <x-nav-link icon="megaphone" :href="route('admin.announcements.index')" :active="request()->routeIs('admin.announcements.*')"><span>الإعلانات</span></x-nav-link>
                <x-nav-link icon="wallet" :href="route('admin.finance.index')" :active="request()->routeIs('admin.finance.*')"><span>العمليات المالية</span></x-nav-link>
                <x-nav-link icon="history" :href="route('admin.audit-logs.index')" :active="request()->routeIs('admin.audit-logs.*')"><span>سجل العمليات</span></x-nav-link>

                <div class="mx-2 my-3 gold-hairline"></div>

                <x-nav-link icon="quran" :href="route('admin.quran-review.index')" :active="request()->routeIs('admin.quran-review.*')"><span>مراجعة القرآن</span></x-nav-link>
                <x-nav-link icon="moon" :href="route('admin.quran.index')" :active="request()->routeIs('admin.quran.index') || request()->routeIs('admin.quran.journey')"><span>البرامج القرآنية</span></x-nav-link>
                <x-nav-link icon="tasmee" :href="route('admin.quran.tasmee.index')" :active="request()->routeIs('admin.quran.tasmee.*')"><span>التسميع</span></x-nav-link>
                <x-nav-link icon="completions" :href="route('admin.quran.completions.index')" :active="request()->routeIs('admin.quran.completions.*')"><span>إتمام الحفظ</span></x-nav-link>
                <x-nav-link icon="hafiz" :href="route('admin.quran.hafiz.index')" :active="request()->routeIs('admin.quran.hafiz.*')"><span>الحفاظ</span></x-nav-link>
                <x-nav-link icon="qualifying" :href="route('admin.quran.qualifying.index')" :active="request()->routeIs('admin.quran.qualifying.*')"><span>البرنامج التأهيلي</span></x-nav-link>
                <x-nav-link icon="ijazah" :href="route('admin.quran.ijazah.index')" :active="request()->routeIs('admin.quran.ijazah.*')"><span>برنامج الإجازة</span></x-nav-link>
                <x-nav-link icon="quran-exams" :href="route('admin.quran.exams.index')" :active="request()->routeIs('admin.quran.exams.*')"><span>اختبارات الحفاظ</span></x-nav-link>
                <x-nav-link icon="faith" :href="route('admin.faith-meetings.index')" :active="request()->routeIs('admin.faith-meetings.*')"><span>اللقاءات الإيمانية</span></x-nav-link>
                <x-nav-link icon="quran" :href="route('admin.sharia-courses.index')" :active="request()->routeIs('admin.sharia-courses.*')"><span>الدورات الشرعية</span></x-nav-link>
                <x-nav-link icon="trophy" :href="route('admin.reward-points.index')" :active="request()->routeIs('admin.reward-points.*')"><span>نقاط المكافآت</span></x-nav-link>

                <div class="mx-2 my-3 gold-hairline"></div>

                <x-nav-link icon="shield" :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')"><span>الحسابات والصلاحيات</span></x-nav-link>
                <x-nav-link icon="clock" :href="route('admin.sessions.index')" :active="request()->routeIs('admin.sessions.*')"><span>الدوامات</span></x-nav-link>
                <x-nav-link icon="bell" :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                    <span class="flex-1">الإشعارات</span>
                    @if($unreadCount > 0)
                        <span class="pulse-dot ms-auto inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-gradient-to-l from-gold-400 to-gold-600 text-pine-950 text-[10px] font-black">{{ $unreadCount }}</span>
                    @endif
                </x-nav-link>
            @elseif($user->isSuperAdmin())
                <x-nav-link icon="home" :href="route('super-admin.dashboard')" :active="request()->routeIs('super-admin.dashboard')">
                    <span>لوحة التحكم</span>
                </x-nav-link>
                <x-nav-link icon="mosque" :href="route('super-admin.mosques.index')" :active="request()->routeIs('super-admin.mosques.*')"><span>الجوامع</span></x-nav-link>
                <x-nav-link icon="bell" :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                    <span class="flex-1">الإشعارات</span>
                    @if($unreadCount > 0)
                        <span class="pulse-dot ms-auto inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-gradient-to-l from-gold-400 to-gold-600 text-pine-950 text-[10px] font-black">{{ $unreadCount }}</span>
                    @endif
                </x-nav-link>
                <div class="pt-3 mt-2 border-t border-white/10 text-[11px] leading-relaxed text-gold-200/70 px-3 flex items-start gap-2">
                    <x-icon name="info" class="w-4 h-4 mt-0.5 shrink-0 text-gold-300/80" />
                    <span>اختر جامعاً من القائمة العلوية لفتح لوحة إدارته الكاملة.</span>
                </div>
            @elseif($user->isGuardian())
                <x-nav-link icon="home" :href="route('guardian.dashboard')" :active="request()->routeIs('guardian.dashboard')"><span>الرئيسية</span></x-nav-link>
                <x-nav-link icon="children" :href="route('guardian.dashboard')" :active="request()->routeIs('guardian.children.*')"><span>أبنائي</span></x-nav-link>
                <x-nav-link icon="user" :href="route('guardian.profile')" :active="request()->routeIs('guardian.profile')"><span>الملف الشخصي</span></x-nav-link>
                <x-nav-link icon="bell" :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                    <span class="flex-1">الإشعارات</span>
                    @if($unreadCount > 0)
                        <span class="pulse-dot ms-auto inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-gradient-to-l from-gold-400 to-gold-600 text-pine-950 text-[10px] font-black">{{ $unreadCount }}</span>
                    @endif
                </x-nav-link>
                <div class="pt-3 mt-2 border-t border-white/10 text-[11px] leading-relaxed text-gold-200/70 px-3 flex items-start gap-2">
                    <x-icon name="info" class="w-4 h-4 mt-0.5 shrink-0 text-gold-300/80" />
                    <span>يمكنك الاطلاع على بيانات أبنائك فقط.</span>
                </div>
            @elseif($user->isStudent())
                <x-nav-link icon="home" :href="route('student.dashboard')" :active="request()->routeIs('student.dashboard')"><span>الرئيسية</span></x-nav-link>
                <x-nav-link icon="user" :href="route('student.profile')" :active="request()->routeIs('student.profile')"><span>ملفي الشخصي</span></x-nav-link>
                <x-nav-link icon="attendance" :href="route('student.attendance')" :active="request()->routeIs('student.attendance')"><span>الحضور والغياب</span></x-nav-link>
                <x-nav-link icon="subjects" :href="route('student.subjects')" :active="request()->routeIs('student.subjects')"><span>موادي الدراسية</span></x-nav-link>
                <x-nav-link icon="teachers" :href="route('student.teachers')" :active="request()->routeIs('student.teachers')"><span>معلموّي</span></x-nav-link>
                <x-nav-link icon="exam" :href="route('student.exams')" :active="request()->routeIs('student.exams')"><span>الامتحانات</span></x-nav-link>
                <x-nav-link icon="grades" :href="route('student.grades')" :active="request()->routeIs('student.grades')"><span>الدرجات</span></x-nav-link>
                <x-nav-link icon="homework" :href="route('student.homeworks')" :active="request()->routeIs('student.homeworks')"><span>الواجبات</span></x-nav-link>
                <x-nav-link icon="megaphone" :href="route('student.announcements')" :active="request()->routeIs('student.announcements')"><span>الإعلانات</span></x-nav-link>
                <x-nav-link icon="bell" :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                    <span class="flex-1">الإشعارات</span>
                    @if($unreadCount > 0)
                        <span class="pulse-dot ms-auto inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-gradient-to-l from-gold-400 to-gold-600 text-pine-950 text-[10px] font-black">{{ $unreadCount }}</span>
                    @endif
                </x-nav-link>
            @else
                <x-nav-link icon="home" :href="route('teacher.dashboard')" :active="request()->routeIs('teacher.dashboard')"><span>الرئيسية</span></x-nav-link>
                <x-nav-link icon="sections" :href="route('teacher.sections.index')" :active="request()->routeIs('teacher.sections.*')"><span>شعبي والطلاب</span></x-nav-link>
                <x-nav-link icon="calendar" :href="route('teacher.schedule')" :active="request()->routeIs('teacher.schedule')"><span>جدولي الدراسي</span></x-nav-link>
                <x-nav-link icon="clock" :href="route('teacher.work-hours.index')" :active="request()->routeIs('teacher.work-hours.*')"><span>ساعات عملي</span></x-nav-link>
                <x-nav-link icon="attendance" :href="route('teacher.attendance.create')" :active="request()->routeIs('teacher.attendance.*')"><span>تسجيل الحضور</span></x-nav-link>
                <x-nav-link icon="homework" :href="route('teacher.homeworks.index')" :active="request()->routeIs('teacher.homeworks.*') || request()->routeIs('teacher.submissions.*')"><span>الواجبات</span></x-nav-link>
                <x-nav-link icon="exam" :href="route('teacher.exams.index')" :active="request()->routeIs('teacher.exams.*') && !request()->routeIs('teacher.grades.*')"><span>الامتحانات</span></x-nav-link>
                <x-nav-link icon="lessons" :href="route('teacher.lessons.index')" :active="request()->routeIs('teacher.lessons.*')"><span>الدروس</span></x-nav-link>
                <x-nav-link icon="chat" :href="route('teacher.messages.index')" :active="request()->routeIs('teacher.messages.*')"><span>الرسائل</span></x-nav-link>
                @if($canSeeFinance)
                    <x-nav-link icon="wallet" :href="route('teacher.finance.index')" :active="request()->routeIs('teacher.finance.*')"><span>المالية</span></x-nav-link>
                @endif

                <div class="mx-2 my-3 gold-hairline"></div>

                <x-nav-link icon="quran" :href="route('teacher.quran-review.index')" :active="request()->routeIs('teacher.quran-review.*')"><span>مراجعة القرآن</span></x-nav-link>
                <x-nav-link icon="moon" :href="route('teacher.quran.index')" :active="request()->routeIs('teacher.quran.*') && !request()->routeIs('teacher.quran-review.*')"><span>القرآن والبرامج</span></x-nav-link>
                <x-nav-link icon="tasmee" :href="route('teacher.quran.tasmee.index')" :active="request()->routeIs('teacher.quran.tasmee.*')"><span>التسميع</span></x-nav-link>
                <x-nav-link icon="qualifying" :href="route('teacher.quran.qualifying.index')" :active="request()->routeIs('teacher.quran.qualifying.*')"><span>البرنامج التأهيلي</span></x-nav-link>
                <x-nav-link icon="ijazah" :href="route('teacher.quran.ijazah.index')" :active="request()->routeIs('teacher.quran.ijazah.*')"><span>برنامج الإجازة</span></x-nav-link>
                <x-nav-link icon="quran-exams" :href="route('teacher.quran.exams.index')" :active="request()->routeIs('teacher.quran.exams.*')"><span>اختبارات الحفاظ</span></x-nav-link>
                <x-nav-link icon="faith" :href="route('teacher.quran.faith-meetings.index')" :active="request()->routeIs('teacher.quran.faith-meetings.*')"><span>اللقاءات الإيمانية</span></x-nav-link>
                <x-nav-link icon="quran" :href="route('teacher.sharia-courses.index')" :active="request()->routeIs('teacher.sharia-courses.*')"><span>الدورات الشرعية</span></x-nav-link>
                <x-nav-link icon="trophy" :href="route('teacher.reward-points.index')" :active="request()->routeIs('teacher.reward-points.*')"><span>نقاط المكافآت</span></x-nav-link>
                <x-nav-link icon="user" :href="route('teacher.profile.edit')" :active="request()->routeIs('teacher.profile.*')"><span>الملف الشخصي</span></x-nav-link>
                <x-nav-link icon="bell" :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                    <span class="flex-1">الإشعارات</span>
                    @if($unreadCount > 0)
                        <span class="pulse-dot ms-auto inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-gradient-to-l from-gold-400 to-gold-600 text-pine-950 text-[10px] font-black">{{ $unreadCount }}</span>
                    @endif
                </x-nav-link>
            @endif
        </nav>

        <div class="relative p-4 border-t border-white/10 bg-pine-950/40 backdrop-blur-sm">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full p-[1.5px] bg-gradient-to-br from-gold-200 to-gold-600 shrink-0">
                    <div class="w-full h-full rounded-full bg-pine-800 grid place-items-center text-gold-200 font-black text-sm overflow-hidden">
                        @if($user->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="w-full h-full object-cover">
                        @else
                            {{ mb_substr($user->name, 0, 1) }}
                        @endif
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-bold truncate">{{ $user->name }}</div>
                    <div class="text-[11px] text-gold-200/80 font-semibold">
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
            <form method="POST" action="{{ route('logout') }}" class="mt-3">
                @csrf
                <button type="submit" class="group w-full flex items-center justify-center gap-2 text-xs text-gold-200/80 hover:text-white transition bg-white/5 hover:bg-white/10 rounded-xl py-2 font-semibold">
                    <x-icon name="logout" class="w-4 h-4 transition-transform duration-300 group-hover:-translate-x-1" />
                    تسجيل الخروج
                </button>
            </form>
        </div>
    </aside>

    <main class="flex-1 min-w-0 px-4 sm:px-6 lg:px-8 py-6 lg:py-8">
        <div class="max-w-[1500px] mx-auto animate-page">
            @if(session('success'))
                <div class="flash-toast relative overflow-hidden mb-6 flex items-center gap-3.5 rounded-2xl bg-white/95 backdrop-blur border border-emerald-200/80 px-4 py-3.5 shadow-[0_18px_40px_-18px_rgba(6,40,29,0.35)]" data-flash>
                    <span class="w-10 h-10 shrink-0 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-700 grid place-items-center text-white shadow-md shadow-emerald-600/30">
                        <x-icon name="check" class="w-5 h-5" />
                    </span>
                    <p class="flex-1 text-emerald-950 font-bold text-sm leading-relaxed">{{ session('success') }}</p>
                    <button type="button" data-flash-close class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition" aria-label="إغلاق">
                        <x-icon name="x" class="w-4 h-4" />
                    </button>
                    <span class="flash-toast-bar bg-gradient-to-l from-emerald-400 via-emerald-500 to-emerald-700" data-flash-bar></span>
                </div>
            @endif

            @if($errors->any())
                <div class="flash-toast relative overflow-hidden mb-6 rounded-2xl bg-white/95 backdrop-blur border border-red-200/80 px-4 py-3.5 shadow-[0_18px_40px_-18px_rgba(120,20,20,0.3)]" data-flash>
                    <div class="flex items-center gap-3.5">
                        <span class="w-10 h-10 shrink-0 rounded-xl bg-gradient-to-br from-red-400 to-red-600 grid place-items-center text-white shadow-md shadow-red-600/30">
                            <x-icon name="alert" class="w-5 h-5" />
                        </span>
                        <div class="flex-1 text-red-950">
                            <div class="font-black text-sm">يرجى تصحيح الأخطاء التالية:</div>
                            <ul class="list-disc pr-5 mt-1 space-y-0.5 text-[13px] font-semibold text-red-800/90">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <button type="button" data-flash-close class="p-1.5 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition" aria-label="إغلاق">
                            <x-icon name="x" class="w-4 h-4" />
                        </button>
                    </div>
                    <span class="flash-toast-bar bg-gradient-to-l from-red-400 via-red-500 to-red-600" data-flash-bar></span>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>
@stack('scripts')
</body>
</html>
