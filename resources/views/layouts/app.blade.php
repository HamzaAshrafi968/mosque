<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'إدارة الجوامع') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-gray-100 min-h-screen font-sans">
<div class="flex min-h-screen">
    <aside class="w-64 bg-emerald-900 text-white flex flex-col shrink-0">
        <div class="p-4 text-xl font-bold border-b border-emerald-800">
            🕌 إدارة الجوامع
        </div>
        <nav class="flex-1 p-2 space-y-1 overflow-y-auto">
            @if(auth()->user()->isAdmin())
                <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">الرئيسية</x-nav-link>
                <x-nav-link :href="route('admin.students.index')" :active="request()->routeIs('admin.students.*')">الطلاب</x-nav-link>
                <x-nav-link :href="route('admin.teachers.index')" :active="request()->routeIs('admin.teachers.*')">المعلمون</x-nav-link>
                <x-nav-link :href="route('admin.classrooms.index')" :active="request()->routeIs('admin.classrooms.*')">الصفوف والشعب</x-nav-link>
                <x-nav-link :href="route('admin.subjects.index')" :active="request()->routeIs('admin.subjects.*')">المواد الدراسية</x-nav-link>
                <x-nav-link :href="route('admin.schedules.index')" :active="request()->routeIs('admin.schedules.*')">الجداول الدراسية</x-nav-link>
                <x-nav-link :href="route('admin.attendance.index')" :active="request()->routeIs('admin.attendance.*')">الحضور والغياب</x-nav-link>
                <x-nav-link :href="route('admin.exams.index')" :active="request()->routeIs('admin.exams.*')">الامتحانات</x-nav-link>
                <x-nav-link :href="route('admin.grades.index')" :active="request()->routeIs('admin.grades.*')">الدرجات</x-nav-link>
                <x-nav-link :href="route('admin.reports.index')" :active="request()->routeIs('admin.reports.*')">التقارير</x-nav-link>
                <x-nav-link :href="route('admin.announcements.index')" :active="request()->routeIs('admin.announcements.*')">الإعلانات</x-nav-link>
                <x-nav-link :href="route('admin.quran-review.index')" :active="request()->routeIs('admin.quran-review.*')">مراجعة القرآن</x-nav-link>
                <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">الحسابات والصلاحيات</x-nav-link>
            @else
                <x-nav-link :href="route('teacher.dashboard')" :active="request()->routeIs('teacher.dashboard')">الرئيسية</x-nav-link>
                <x-nav-link :href="route('teacher.schedule')" :active="request()->routeIs('teacher.schedule')">جدولي الدراسي</x-nav-link>
                <x-nav-link :href="route('teacher.attendance.create')" :active="request()->routeIs('teacher.attendance.*')">تسجيل الحضور</x-nav-link>
                <x-nav-link :href="route('teacher.homeworks.index')" :active="request()->routeIs('teacher.homeworks.*') || request()->routeIs('teacher.submissions.*')">الواجبات</x-nav-link>
                <x-nav-link :href="route('teacher.exams.index')" :active="request()->routeIs('teacher.exams.*') && !request()->routeIs('teacher.grades.*')">الامتحانات</x-nav-link>
                <x-nav-link :href="route('teacher.lessons.index')" :active="request()->routeIs('teacher.lessons.*')">الدروس</x-nav-link>
                <x-nav-link :href="route('teacher.messages.index')" :active="request()->routeIs('teacher.messages.*')">الرسائل</x-nav-link>
                <x-nav-link :href="route('teacher.quran-review.index')" :active="request()->routeIs('teacher.quran-review.*')">مراجعة القرآن</x-nav-link>
                <x-nav-link :href="route('teacher.profile.edit')" :active="request()->routeIs('teacher.profile.*')">الملف الشخصي</x-nav-link>
            @endif
        </nav>
        <div class="p-4 border-t border-emerald-800">
            <div class="text-sm mb-2">{{ auth()->user()->name }}</div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-emerald-300 hover:text-white">تسجيل الخروج</button>
            </form>
        </div>
    </aside>

    <main class="flex-1 p-6 overflow-x-auto">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">@yield('title')</h1>

        @if(session('success'))
            <div class="bg-green-100 border border-green-300 text-green-800 rounded-lg px-4 py-3 mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-100 border border-red-300 text-red-800 rounded-lg px-4 py-3 mb-4">
                <ul class="list-disc pr-5">
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
