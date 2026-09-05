<div class="bg-gradient-to-l from-emerald-700 to-teal-700 text-white rounded-2xl shadow-lg p-5 mb-6 flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-white/20 flex items-center justify-center text-2xl font-bold">👤</div>
        <div>
            <div class="text-xl font-bold">{{ $child->name }}</div>
            <div class="text-sm text-emerald-100">
                {{ $child->classroom?->name ?? '—' }}
                @if($child->section)
                    — شعبة {{ $child->section->name }}
                @endif
            </div>
        </div>
    </div>
    <a href="{{ route('guardian.children.overview', $child) }}" class="text-sm bg-white/15 hover:bg-white/25 transition rounded-lg px-3 py-2">الصفحة الرئيسية للطالب</a>
</div>

<div class="flex flex-wrap gap-2 mb-6">
    @php
        $tabs = [
            'attendance' => ['الحضور', 'guardian.children.attendance'],
            'subjects' => ['المواد', 'guardian.children.subjects'],
            'teachers' => ['المعلمون', 'guardian.children.teachers'],
            'exams' => ['الامتحانات', 'guardian.children.exams'],
            'grades' => ['الدرجات', 'guardian.children.grades'],
            'homeworks' => ['الواجبات', 'guardian.children.homeworks'],
            'announcements' => ['الإعلانات', 'guardian.children.announcements'],
        ];
    @endphp
    @foreach($tabs as $key => [$label, $routeName])
        <a href="{{ route($routeName, $child) }}"
           class="px-4 py-2 rounded-xl text-sm font-medium transition
                  {{ request()->routeIs("guardian.children.$key") ? 'bg-emerald-700 text-white shadow' : 'bg-white text-gray-700 hover:bg-emerald-50' }}">
            {{ $label }}
        </a>
    @endforeach
</div>
