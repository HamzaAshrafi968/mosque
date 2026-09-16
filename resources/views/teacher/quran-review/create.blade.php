@extends('layouts.app')

@section('title', 'الاستماع مع المعلم')

<x-quran-review-styles />

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    @if($pages->isEmpty())
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 card-hover animate-scale-in">
        <div class="ornament-top"></div>
        <h2 class="text-xl font-bold text-gray-800 mb-6 text-center">إعداد جلسة استماع جديدة</h2>

        @if($rangeError)
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-medium px-4 py-3">
                {{ $rangeError }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-5 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-medium px-4 py-3 space-y-1">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="GET" action="{{ route('teacher.quran-review.create') }}" class="space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">👨‍🎓 اختر الطالب</label>
                    <select name="student_id" required class="w-full rounded-xl border-gray-200 bg-gray-50 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition">
                        <option value="">-- اختر الطالب --</option>
                        @foreach($students as $student)
                            <option value="{{ $student->id }}" {{ $studentId == $student->id ? 'selected' : '' }}>
                                {{ $student->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">📅 تاريخ الاستماع</label>
                    <input type="date" name="date" value="{{ $date }}" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition">
                </div>

                <x-quran-page-range
                    :from="$fromPage"
                    :to="$toPage"
                    label="📖 نطاق الصفحات (من صفحة → إلى صفحة)"
                    hint="يتم الاستماع إلى آيات الصفحات المحددة صفحةً صفحة (حتى 20 صفحة لكل جلسة)، ويمكن أن يمتد النطاق بين سورتين."
                    required />

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">📝 ملاحظات</label>
                    <input type="text" name="notes" value="{{ $notes }}" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition" placeholder="ملاحظات عامة...">
                </div>
            </div>
            <div class="flex justify-center pt-2">
                <button type="submit" class="bg-gradient-to-r from-emerald-700 to-emerald-600 text-white px-10 py-3 rounded-xl hover:from-emerald-800 hover:to-emerald-700 transition text-lg font-bold shadow-lg shadow-emerald-700/25">
                    ✨ بدء الاستماع
                </button>
            </div>
        </form>
    </div>
    @else
    <div class="flex items-center gap-3 mb-2 animate-slide-right">
        <a href="{{ route('teacher.quran.batches.index') }}" class="text-emerald-600 hover:text-emerald-800 text-sm font-medium transition">
            ← العودة إلى جلسات الاستماع
        </a>
        <span class="text-gray-300">|</span>
        <span class="text-gray-500 text-sm">
            جلسة استماع جديدة — {{ $fromPage === $toPage ? 'صفحة '.$fromPage : 'صفحة '.$fromPage.' → صفحة '.$toPage }}
        </span>
    </div>

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm font-medium px-4 py-3 space-y-1">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form id="review-form" method="POST" action="{{ route('teacher.quran-review.store') }}">
        @csrf
        <input type="hidden" name="student_id" value="{{ $studentId }}">
        <input type="hidden" name="from_page" value="{{ $fromPage }}">
        <input type="hidden" name="to_page" value="{{ $toPage }}">
        <input type="hidden" name="date" value="{{ $date }}">
        <input type="hidden" name="notes" value="{{ $notes }}">

        {{-- Legend --}}
        <div class="status-legend mb-4 animate-fade-in-up">
            <span class="text-sm font-bold text-gray-700">⌨️ دليل الألوان والمفاتيح:</span>
            <span class="legend-item"><span class="legend-dot status-correct"></span> ✅ صحيحة (افتراضي)</span>
            <span class="legend-item"><span class="legend-dot status-correct"></span> <span class="shortcut-key">1</span> صحيحة</span>
            <span class="legend-item"><span class="legend-dot status-incorrect"></span> <span class="shortcut-key">2</span> خطأ نطق</span>
            <span class="legend-item"><span class="legend-dot status-hesitation"></span> <span class="shortcut-key">3</span> تردد</span>
            <span class="legend-item"><span class="legend-dot status-tajweed_error"></span> <span class="shortcut-key">4</span> خطأ تجويد</span>
            <span class="legend-item"><span class="legend-dot status-added"></span> <span class="shortcut-key">5</span> زيادة</span>
            <span class="legend-item"><span class="legend-dot status-forgotten"></span> <span class="shortcut-key">6</span> نسيان</span>
        </div>

        {{-- Page navigation --}}
        @php
            $toArabicDigits = static fn (int $number): string => strtr((string) $number, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
        @endphp
        @if($pages->count() > 1)
        <div id="page-nav" class="sticky top-2 z-40 bg-white/95 backdrop-blur rounded-2xl shadow-sm border border-gray-200 px-4 py-3 mb-4 animate-fade-in-up">
            <div class="flex items-center justify-between gap-3">
                <button type="button" id="page-prev" onclick="goToReviewPage(currentReviewPage - 1)"
                        class="px-4 py-2 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-bold text-sm transition disabled:opacity-40 disabled:cursor-not-allowed">
                    → السابقة
                </button>
                <div class="text-center">
                    <div class="text-sm font-bold text-gray-700">
                        صفحة الاستماع <span id="page-current" class="text-emerald-700">١</span> من <span id="page-total">{{ $toArabicDigits($pages->count()) }}</span>
                    </div>
                    <div class="text-[11px] text-gray-400">صفحة المصحف <span id="page-mushaf">{{ $toArabicDigits($pages->first()['page']) }}</span></div>
                </div>
                <button type="button" id="page-next" onclick="goToReviewPage(currentReviewPage + 1)"
                        class="px-4 py-2 rounded-xl bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-bold text-sm transition disabled:opacity-40 disabled:cursor-not-allowed">
                    التالية ←
                </button>
            </div>
            <div id="page-dots" class="flex flex-wrap justify-center gap-1.5 mt-3">
                @foreach($pages as $i => $pageData)
                    <button type="button" data-page-target="{{ $i }}" onclick="goToReviewPage({{ $i }})"
                            class="w-8 h-8 rounded-lg text-xs font-bold transition {{ $i === 0 ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-emerald-50' }}">
                        {{ $toArabicDigits($pageData['page']) }}
                    </button>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Mushaf pages (one page at a time) --}}
        <div id="ayahs-container" class="space-y-4 animate-fade-in-up">
            @foreach($pages as $i => $pageData)
                <div data-review-step="{{ $i }}" data-page="{{ $pageData['page'] }}" class="{{ $i === 0 ? '' : 'hidden' }}">
                    <x-quran-review-page
                        :page="$pageData['page']"
                        :ayahs="$pageData['ayahs']"
                        :surah-starts="$pageData['surahStarts']"
                        interactive />
                </div>
            @endforeach
        </div>

        {{-- Stats Summary --}}
        <div class="mt-6 bg-white rounded-2xl shadow-sm border border-gray-200 p-8 card-hover animate-fade-in-up">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                📊 ملخص الاستماع
            </h3>
            <div class="review-stats mb-4">
                <div class="stat-card">
                    <div class="text-gray-500 text-xs font-medium">الإجمالي</div>
                    <div class="stat-value text-gray-700" id="stat-total">0</div>
                </div>
                <div class="stat-card">
                    <div class="text-gray-500 text-xs font-medium">✅ صحيحة</div>
                    <div class="stat-value text-emerald-600" id="stat-correct">0</div>
                </div>
                <div class="stat-card">
                    <div class="text-gray-500 text-xs font-medium">❌ أخطاء النطق</div>
                    <div class="stat-value text-red-600" id="stat-incorrect">0</div>
                </div>
                <div class="stat-card">
                    <div class="text-gray-500 text-xs font-medium">🟡 تردد</div>
                    <div class="stat-value text-yellow-600" id="stat-hesitation">0</div>
                </div>
                <div class="stat-card">
                    <div class="text-gray-500 text-xs font-medium">🔵 أخطاء التجويد</div>
                    <div class="stat-value text-blue-600" id="stat-tajweed_error">0</div>
                </div>
                <div class="stat-card bg-emerald-50 border-emerald-200">
                    <div class="text-emerald-700 text-xs font-bold">🎯 نسبة الإتقان</div>
                    <div class="stat-value text-emerald-700" id="stat-mastery">100%</div>
                    <div class="mastery-bar mt-2">
                        <div class="mastery-fill" id="mastery-bar" style="width: 100%"></div>
                    </div>
                </div>
            </div>
            <div class="flex justify-center">
                <button type="submit" class="bg-gradient-to-r from-emerald-600 to-emerald-500 text-white px-10 py-3.5 rounded-xl hover:from-emerald-700 hover:to-emerald-600 transition text-lg font-bold shadow-lg shadow-emerald-600/30">
                    💾 حفظ الاستماع
                </button>
            </div>
        </div>
    </form>

    {{-- Error type popup --}}
    <div id="error-popup" class="error-popup hidden">
        <div class="text-sm font-bold text-gray-700 mb-2 pb-2 border-b">اختر نوع الخطأ:</div>
        <div class="grid grid-cols-2 gap-1.5">
            <button onclick="setWordStatus('correct')" class="px-3 py-2.5 rounded-xl text-sm font-medium bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 transition">
                ✅ صحيحة
            </button>
            <button onclick="setWordStatus('incorrect')" class="px-3 py-2.5 rounded-xl text-sm font-medium bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 transition">
                ❌ خطأ نطق
            </button>
            <button onclick="setWordStatus('hesitation')" class="px-3 py-2.5 rounded-xl text-sm font-medium bg-yellow-50 hover:bg-yellow-100 text-yellow-700 border border-yellow-200 transition">
                🟡 تردد
            </button>
            <button onclick="setWordStatus('tajweed_error')" class="px-3 py-2.5 rounded-xl text-sm font-medium bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 transition">
                🔵 خطأ تجويد
            </button>
            <button onclick="setWordStatus('added')" class="px-3 py-2.5 rounded-xl text-sm font-medium bg-pink-50 hover:bg-pink-100 text-pink-700 border border-pink-200 transition">
                ➕ زيادة
            </button>
            <button onclick="setWordStatus('forgotten')" class="px-3 py-2.5 rounded-xl text-sm font-medium bg-orange-50 hover:bg-orange-100 text-orange-700 border border-orange-200 transition">
                ➖ نسيان
            </button>
        </div>
        <button onclick="hidePopup()" class="mt-3 w-full text-center text-xs text-gray-400 hover:text-gray-600 py-1.5 bg-gray-50 rounded-lg transition">إلغاء | Esc</button>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    let currentWordElement = null;
    let currentReviewPage = 0;

    document.querySelectorAll('.quran-word').forEach((el, index) => {
        el.dataset.globalIndex = index;
    });

    function toArabicDigits(value) {
        return String(value).replace(/[0-9]/g, d => '٠١٢٣٤٥٦٧٨٩'[d]);
    }

    function goToReviewPage(index, scroll = true) {
        const steps = document.querySelectorAll('[data-review-step]');
        if (!steps.length || index < 0 || index >= steps.length) return;

        currentReviewPage = index;
        steps.forEach((step, i) => step.classList.toggle('hidden', i !== index));

        const current = document.getElementById('page-current');
        if (current) current.textContent = toArabicDigits(index + 1);

        const mushaf = document.getElementById('page-mushaf');
        if (mushaf) mushaf.textContent = toArabicDigits(steps[index].dataset.page);

        const total = document.getElementById('page-total');
        if (total) total.textContent = toArabicDigits(steps.length);

        const prev = document.getElementById('page-prev');
        const next = document.getElementById('page-next');
        if (prev) prev.disabled = index === 0;
        if (next) next.disabled = index === steps.length - 1;

        document.querySelectorAll('#page-dots [data-page-target]').forEach(dot => {
            const active = parseInt(dot.dataset.pageTarget, 10) === index;
            dot.className = 'w-8 h-8 rounded-lg text-xs font-bold transition ' +
                (active ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-emerald-50');
        });

        if (scroll) {
            const nav = document.getElementById('page-nav');
            if (nav) {
                window.scrollTo({ top: nav.getBoundingClientRect().top + window.scrollY - 12, behavior: 'smooth' });
            }
        }

        updateStats();
    }

    function toggleWordError(el, event) {
        event.preventDefault();
        event.stopPropagation();
        currentWordElement = el;

        const popup = document.getElementById('error-popup');
        popup.classList.remove('hidden');

        const rect = el.getBoundingClientRect();
        const popupWidth = 250;
        let left = rect.left + window.scrollX;
        let top = rect.bottom + window.scrollY + 8;

        if (left + popupWidth > window.innerWidth) {
            left = window.innerWidth - popupWidth - 10;
        }
        if (top + 250 > window.innerHeight + window.scrollY) {
            top = rect.top + window.scrollY - 260;
        }
        if (left < 10) left = 10;
        if (top < 10) top = 10;

        popup.style.left = left + 'px';
        popup.style.top = top + 'px';

        document.querySelectorAll('.quran-word').forEach(w => w.classList.remove('active-word'));
        el.classList.add('active-word');
    }

    function setWordStatus(status) {
        if (!currentWordElement) return;

        currentWordElement.className = 'quran-word status-' + status;
        currentWordElement.dataset.status = status;

        const idx = parseInt(currentWordElement.dataset.globalIndex);
        const existingInput = document.querySelector(`input[name="word_statuses[]"][data-word-index="${idx}"]`);

        if (existingInput) {
            existingInput.value = status;
        } else {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'word_statuses[]';
            input.value = status;
            input.dataset.wordIndex = String(idx);

            let container = document.getElementById('word-statuses-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'word-statuses-container';
                document.getElementById('review-form').appendChild(container);
            }
            container.appendChild(input);
        }

        hidePopup();
        updateStats();
    }

    function hidePopup() {
        const popup = document.getElementById('error-popup');
        if (!popup) return;
        popup.classList.add('hidden');
        document.querySelectorAll('.quran-word').forEach(w => w.classList.remove('active-word'));
        currentWordElement = null;
    }

    function updateStats() {
        const words = document.querySelectorAll('.quran-word');
        let total = 0, correct = 0, incorrect = 0, hesitation = 0, tajweed = 0, added = 0, forgotten = 0;

        words.forEach(w => {
            total++;
            const status = w.dataset.status || w.className.match(/status-(\w+)/)?.[1] || 'correct';
            if (status === 'correct') correct++;
            if (status === 'incorrect') incorrect++;
            if (status === 'hesitation') hesitation++;
            if (status === 'tajweed_error') tajweed++;
            if (status === 'added') added++;
            if (status === 'forgotten') forgotten++;
        });

        document.getElementById('stat-total').textContent = total;
        document.getElementById('stat-correct').textContent = correct;
        document.getElementById('stat-incorrect').textContent = incorrect;
        document.getElementById('stat-hesitation').textContent = hesitation;
        document.getElementById('stat-tajweed_error').textContent = tajweed;

        const mastery = total > 0 ? Math.round((correct / total) * 100) : 100;
        document.getElementById('stat-mastery').textContent = mastery + '%';
        document.getElementById('mastery-bar').style.width = mastery + '%';

        const masteryEl = document.getElementById('stat-mastery');
        masteryEl.className = mastery >= 90 ? 'stat-value text-emerald-600' :
                             mastery >= 70 ? 'stat-value text-yellow-600' :
                                           'stat-value text-red-600';
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#error-popup') && !e.target.closest('.quran-word')) {
            hidePopup();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (!currentWordElement) {
            if (e.key === 'PageDown') { e.preventDefault(); goToReviewPage(currentReviewPage + 1); }
            if (e.key === 'PageUp') { e.preventDefault(); goToReviewPage(currentReviewPage - 1); }
            return;
        }
        const keys = {
            '1': 'correct',
            '2': 'incorrect',
            '3': 'hesitation',
            '4': 'tajweed_error',
            '5': 'added',
            '6': 'forgotten',
            'Escape': null,
        };
        if (keys[e.key] === null) { hidePopup(); return; }
        if (keys[e.key]) setWordStatus(keys[e.key]);
    });

    document.getElementById('review-form')?.addEventListener('submit', function(e) {
        const allWords = document.querySelectorAll('.quran-word');
        allWords.forEach(w => {
            const status = w.dataset.status || w.className.match(/status-(\w+)/)?.[1] || 'correct';
            const idx = parseInt(w.dataset.globalIndex);

            const existing = document.querySelector(`input[name="word_statuses[]"][data-word-index="${idx}"]`);
            if (!existing) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'word_statuses[]';
                input.value = status;
                input.dataset.wordIndex = String(idx);
                this.appendChild(input);
            }
        });

        const inputs = Array.from(this.querySelectorAll('input[name="word_statuses[]"]'));
        inputs.sort((a, b) => parseInt(a.dataset.wordIndex) - parseInt(b.dataset.wordIndex));
        inputs.forEach(inp => this.appendChild(inp));
    });

    if (document.getElementById('review-form')) {
        updateStats();
        if (document.querySelector('[data-review-step]')) {
            goToReviewPage(0, false);
        }
    }
</script>
@endpush
