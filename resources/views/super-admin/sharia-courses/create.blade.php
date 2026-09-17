@extends('layouts.app')

@section('title', 'دورة شرعية جديدة — مدير الجوامع')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <a href="{{ route('super-admin.sharia-courses.index') }}" class="text-sm text-emerald-700 hover:text-emerald-800">← الدورات الشرعية</a>
    <div>
        <h2 class="text-2xl font-extrabold text-gray-800">إنشاء دورة شرعية وربطها بجامع</h2>
        <p class="text-sm text-gray-500 mt-1">حدّد الجامع (المكان) والمشرفين والطلاب، وسيصل إشعار لمدير الجامع بأن الدورة أُضيفت من مدير الجوامع.</p>
    </div>

    <form method="POST" action="{{ route('super-admin.sharia-courses.store') }}" class="space-y-5">
        @csrf

        {{-- خصائص الدورة --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-4">
            <h3 class="font-black text-gray-800">🏛️ خصائص الدورة والجامع</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">الجامع (المكان) <span class="text-red-500">*</span></label>
                    <select name="mosque_id" id="mosque-select" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        <option value="">— اختر الجامع —</option>
                        @foreach($mosques as $mosque)
                            <option value="{{ $mosque->id }}" @selected(old('mosque_id') === $mosque->id)>{{ $mosque->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">سيُنشأ إشعار لمدير هذا الجامع فور الحفظ.</p>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">اسم الدورة <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required maxlength="255" value="{{ old('name') }}" placeholder="مثال: دورة الفقه المكثفة" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">المكان التفصيلي</label>
                    <input type="text" name="location" maxlength="255" value="{{ old('location') }}" placeholder="افتراضياً: اسم الجامع" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">الحالة <span class="text-red-500">*</span></label>
                    <select name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        @foreach($statuses as $status)
                            <option value="{{ $status->value }}" @selected(old('status', 'active') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">تاريخ البداية</label>
                        <input type="date" name="start_date" value="{{ old('start_date') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 mb-1">تاريخ النهاية</label>
                        <input type="date" name="end_date" value="{{ old('end_date') }}" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-1">الوصف</label>
                    <textarea name="description" rows="3" maxlength="5000" class="w-full border border-gray-300 rounded-lg px-3 py-2">{{ old('description') }}</textarea>
                </div>
            </div>
        </div>

        {{-- المشرفون --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-3">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <h3 class="font-black text-gray-800">🧑‍🏫 مشرفو الدورة (يمكن اختيار أكثر من مشرف)</h3>
                <span class="text-xs text-gray-400">المحددون: <span id="supervisor-count">0</span></span>
            </div>
            <input type="text" id="supervisor-filter" placeholder="بحث سريع باسم المشرف..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <p id="supervisors-placeholder" class="text-sm text-gray-400 py-3">اختر الجامع أولاً لعرض مشرفيه.</p>
            <div id="supervisors-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 max-h-72 overflow-y-auto border border-gray-100 rounded-xl p-3 hidden"></div>
            <input type="hidden" name="supervisor_ids[]" value="">
        </div>

        {{-- الطلاب --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 space-y-4">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <h3 class="font-black text-gray-800">🎓 طلاب الدورة</h3>
                <span class="text-xs text-gray-400">المحددون من الموجودين: <span id="student-count">0</span> + الجدد: <span id="new-student-count">0</span></span>
            </div>

            <div class="space-y-2">
                <div class="text-sm font-bold text-gray-600">تسجيل طلاب موجودين في الجامع</div>
                <input type="text" id="student-filter" placeholder="بحث سريع باسم الطالب..." class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <p id="students-placeholder" class="text-sm text-gray-400 py-3">اختر الجامع أولاً لعرض طلابه.</p>
                <div id="students-list" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 max-h-80 overflow-y-auto border border-gray-100 rounded-xl p-3 hidden"></div>
                <input type="hidden" name="student_ids[]" value="">
            </div>

            <div class="border-t border-gray-100 pt-4 space-y-3">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div class="text-sm font-bold text-gray-600">إضافة طلاب جدد (غير موجودين في الجامع)</div>
                    <button type="button" id="add-new-student" class="bg-white border border-emerald-600 text-emerald-700 hover:bg-emerald-50 text-xs font-bold px-3 py-1.5 rounded-lg">+ إضافة طالب جديد</button>
                </div>
                <div id="new-students-list" class="space-y-2"></div>
            </div>
        </div>

        <div class="flex items-center justify-between">
            <button type="submit" class="bg-emerald-700 hover:bg-emerald-800 text-white font-bold px-8 py-2.5 rounded-xl">إنشاء الدورة وإشعار مدير الجامع</button>
            <a href="{{ route('super-admin.sharia-courses.index') }}" class="text-gray-500 text-sm hover:underline">إلغاء</a>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
    const optionsUrl = @json(route('super-admin.sharia-courses.options'));
    const oldSupervisors = @json(array_values(array_filter(old('supervisor_ids', []), fn ($id) => filled($id))));
    const oldStudents = @json(array_values(array_filter(old('student_ids', []), fn ($id) => filled($id))));
    const oldNewStudents = @json(old('new_students', []));

    const mosqueSelect = document.getElementById('mosque-select');
    const supervisorsList = document.getElementById('supervisors-list');
    const supervisorsPlaceholder = document.getElementById('supervisors-placeholder');
    const studentsList = document.getElementById('students-list');
    const studentsPlaceholder = document.getElementById('students-placeholder');
    const newStudentsList = document.getElementById('new-students-list');

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

    function updateCounts() {
        document.getElementById('supervisor-count').textContent = document.querySelectorAll('.supervisor-check:checked').length;
        document.getElementById('student-count').textContent = document.querySelectorAll('.student-check:checked').length;
        document.getElementById('new-student-count').textContent = document.querySelectorAll('.new-student-row').length;
    }

    function renderSupervisors(items) {
        supervisorsList.innerHTML = items.map((teacher) => `
            <label class="flex items-start gap-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg px-2 py-1.5 supervisor-option">
                <input type="checkbox" name="supervisor_ids[]" value="${escapeHtml(teacher.id)}" class="mt-1 accent-emerald-600 supervisor-check" ${oldSupervisors.includes(String(teacher.id)) ? 'checked' : ''}>
                <span>
                    <span class="font-bold">${escapeHtml(teacher.name)}</span>
                    ${teacher.specialty ? `<span class="block text-xs text-gray-400">${escapeHtml(teacher.specialty)}</span>` : ''}
                </span>
            </label>`).join('');
        supervisorsList.classList.toggle('hidden', items.length === 0);
        supervisorsPlaceholder.classList.toggle('hidden', items.length > 0);
        supervisorsPlaceholder.textContent = items.length === 0 ? 'لا يوجد مشرفون نشطون في هذا الجامع.' : '';
        updateCounts();
    }

    function renderStudents(items) {
        studentsList.innerHTML = items.map((student) => `
            <label class="flex items-center gap-2 text-sm text-gray-700 hover:bg-gray-50 rounded-lg px-2 py-1.5 student-option">
                <input type="checkbox" name="student_ids[]" value="${escapeHtml(student.id)}" class="accent-emerald-600 student-check" ${oldStudents.includes(String(student.id)) ? 'checked' : ''}>
                <span class="font-bold">${escapeHtml(student.name)}</span>
                ${student.classroom ? `<span class="text-xs text-gray-400">${escapeHtml(student.classroom.name)}</span>` : ''}
            </label>`).join('');
        studentsList.classList.toggle('hidden', items.length === 0);
        studentsPlaceholder.classList.toggle('hidden', items.length > 0);
        studentsPlaceholder.textContent = items.length === 0 ? 'لا يوجد طلاب نشطون في هذا الجامع.' : '';
        updateCounts();
    }

    async function loadMosqueOptions() {
        const mosqueId = mosqueSelect.value;
        if (!mosqueId) {
            supervisorsList.innerHTML = '';
            studentsList.innerHTML = '';
            renderSupervisors([]);
            renderStudents([]);
            return;
        }

        supervisorsPlaceholder.textContent = 'جارٍ التحميل...';
        supervisorsPlaceholder.classList.remove('hidden');
        studentsPlaceholder.textContent = 'جارٍ التحميل...';
        studentsPlaceholder.classList.remove('hidden');

        try {
            const response = await fetch(`${optionsUrl}?mosque_id=${encodeURIComponent(mosqueId)}`, {
                headers: { 'Accept': 'application/json' },
            });
            const data = await response.json();
            renderSupervisors(data.supervisors ?? []);
            renderStudents(data.students ?? []);
        } catch (error) {
            supervisorsPlaceholder.textContent = 'تعذّر تحميل بيانات الجامع.';
            studentsPlaceholder.textContent = 'تعذّر تحميل بيانات الجامع.';
        }
    }

    function reindexNewStudents() {
        newStudentsList.querySelectorAll('.new-student-row').forEach((row, index) => {
            row.querySelectorAll('[name^="new_students["]').forEach((input) => {
                input.name = input.name.replace(/^new_students\[\d+\]/, `new_students[${index}]`);
            });
        });
        updateCounts();
    }

    function addNewStudentRow(values = {}) {
        const index = newStudentsList.querySelectorAll('.new-student-row').length;
        const wrapper = document.createElement('div');
        wrapper.className = 'new-student-row bg-gray-50 border border-gray-200 rounded-xl p-3 space-y-2';
        wrapper.innerHTML = `
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-gray-500">طالب جديد</span>
                <button type="button" class="remove-new-student text-xs text-red-600 hover:underline">إزالة</button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                <input type="text" name="new_students[${index}][name]" value="${escapeHtml(values.name)}" required maxlength="255" placeholder="الاسم *" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                <input type="text" name="new_students[${index}][phone]" value="${escapeHtml(values.phone)}" maxlength="30" placeholder="الجوال" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                <select name="new_students[${index}][gender]" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                    <option value="">— الجنس —</option>
                    <option value="male" ${values.gender === 'male' ? 'selected' : ''}>ذكر</option>
                    <option value="female" ${values.gender === 'female' ? 'selected' : ''}>أنثى</option>
                </select>
                <input type="date" name="new_students[${index}][birth_date]" value="${escapeHtml(values.birth_date)}" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm" aria-label="تاريخ الميلاد">
                <input type="text" name="new_students[${index}][guardian_phone]" value="${escapeHtml(values.guardian_phone)}" maxlength="30" placeholder="جوال ولي الأمر" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
                <input type="text" name="new_students[${index}][notes]" value="${escapeHtml(values.notes)}" maxlength="2000" placeholder="ملاحظات" class="w-full border border-gray-300 rounded-lg px-2.5 py-2 text-sm">
            </div>`;
        newStudentsList.appendChild(wrapper);
        wrapper.querySelector('.remove-new-student').addEventListener('click', () => {
            wrapper.remove();
            reindexNewStudents();
        });
        updateCounts();
    }

    mosqueSelect.addEventListener('change', loadMosqueOptions);
    document.getElementById('add-new-student').addEventListener('click', () => addNewStudentRow());

    document.getElementById('supervisor-filter').addEventListener('input', (event) => {
        const query = event.target.value.trim();
        supervisorsList.querySelectorAll('.supervisor-option').forEach((option) => {
            option.classList.toggle('hidden', query !== '' && !option.textContent.includes(query));
        });
    });

    document.getElementById('student-filter').addEventListener('input', (event) => {
        const query = event.target.value.trim();
        studentsList.querySelectorAll('.student-option').forEach((option) => {
            option.classList.toggle('hidden', query !== '' && !option.textContent.includes(query));
        });
    });

    supervisorsList.addEventListener('change', updateCounts);
    studentsList.addEventListener('change', updateCounts);

    oldNewStudents.forEach((row) => addNewStudentRow(row));

    if (mosqueSelect.value) {
        loadMosqueOptions();
    }
</script>
@endsection
