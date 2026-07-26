@extends('layouts.app')

@section('title', 'مراجعة القرآن الكريم')

@push('styles')
<style>
    .quran-word {
        display: inline-block;
        padding: 5px 10px;
        margin: 2px;
        border-radius: 10px;
        cursor: pointer;
        font-size: 1.6rem;
        font-family: 'Amiri', 'Scheherazade New', 'Traditional Arabic', 'UthmanicHafs', serif;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        user-select: none;
        line-height: 2.5;
        position: relative;
    }
    .quran-word:hover {
        transform: scale(1.08) translateY(-1px);
        box-shadow: 0 4px 14px rgba(0,0,0,0.12);
    }
    .quran-word.status-unreviewed  { background: #f1f5f9; color: #64748b; border: 1px dashed #cbd5e1; }
    .quran-word.status-correct     { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; border: 1px solid #86efac; }
    .quran-word.status-incorrect   { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #991b1b; border: 1px solid #fca5a5; }
    .quran-word.status-hesitation  { background: linear-gradient(135deg, #fef9c3, #fef08a); color: #854d0e; border: 1px solid #fde047; }
    .quran-word.status-tajweed_error { background: linear-gradient(135deg, #dbeafe, #bfdbfe); color: #1e40af; border: 1px solid #93c5fd; }
    .quran-word.status-added       { background: linear-gradient(135deg, #fce7f3, #fbcfe8); color: #9d174d; border: 1px solid #f9a8d4; }
    .quran-word.status-forgotten   { background: linear-gradient(135deg, #ffedd5, #fed7aa); color: #9a3412; border: 1px solid #fdba74; }
    .quran-word.active-word {
        outline: 3px solid #7c3aed;
        outline-offset: 2px;
        z-index: 30;
        transform: scale(1.1);
        box-shadow: 0 4px 18px rgba(124, 58, 237, 0.3);
    }
    .error-popup {
        position: absolute;
        z-index: 50;
        background: white;
        border-radius: 16px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.2), 0 0 0 1px rgba(0,0,0,0.05);
        padding: 14px;
        min-width: 230px;
        animation: scaleIn 0.2s ease-out;
    }
    .error-popup button {
        transition: all 0.15s ease;
    }
    .error-popup button:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .status-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        padding: 14px 18px;
        background: white;
        border-radius: 16px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .legend-item {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 0.8rem;
        color: #4b5563;
    }
    .legend-dot {
        width: 16px;
        height: 16px;
        border-radius: 5px;
        border: 1px solid rgba(0,0,0,0.1);
    }
    .ayah-container {
        background: white;
        border-radius: 16px;
        padding: 20px 24px;
        margin-bottom: 10px;
        border: 1px solid #e5e7eb;
        text-align: right;
        direction: rtl;
        line-height: 2.8;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
        transition: box-shadow 0.3s ease;
    }
    .ayah-container:hover {
        box-shadow: 0 6px 18px rgba(0,0,0,0.06);
    }
    .ayah-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        border-radius: 50%;
        font-size: 0.8rem;
        font-weight: bold;
        margin-left: 10px;
        vertical-align: middle;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
    }
    .review-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 10px;
    }
    .stat-card {
        background: white;
        border-radius: 14px;
        padding: 14px 12px;
        text-align: center;
        border: 1px solid #e5e7eb;
        transition: all 0.3s ease;
    }
    .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.06);
    }
    .stat-value {
        font-size: 1.6rem;
        font-weight: 800;
    }
    .mastery-bar {
        height: 10px;
        border-radius: 5px;
        background: #e5e7eb;
        overflow: hidden;
        margin-top: 8px;
    }
    .mastery-fill {
        height: 100%;
        border-radius: 5px;
        background: linear-gradient(90deg, #10b981, #34d399, #6ee7b7);
        transition: width 0.4s ease;
    }
    .shortcut-key {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 24px;
        height: 24px;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        border-radius: 5px;
        font-size: 0.7rem;
        font-weight: bold;
        color: #64748b;
        padding: 0 5px;
    }
    .surah-header {
        background: linear-gradient(135deg, #064e3b, #065f46, #047857);
        border-radius: 20px;
        padding: 20px 28px;
        color: white;
        box-shadow: 0 4px 20px rgba(6, 78, 59, 0.2);
    }
</style>
@endpush

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    @if(!$ayahs->count())
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 card-hover animate-scale-in">
        <div class="ornament-top"></div>
        <h2 class="text-xl font-bold text-gray-800 mb-6 text-center">إعداد جلسة مراجعة جديدة</h2>
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
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">📖 اختر السورة</label>
                    <select name="surah_id" required class="w-full rounded-xl border-gray-200 bg-gray-50 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition">
                        <option value="">-- اختر السورة --</option>
                        @foreach($surahs as $s)
                            <option value="{{ $s->id }}" {{ $surahId == $s->id ? 'selected' : '' }}>
                                {{ $s->name_arabic }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">🔢 من آية</label>
                    <input type="number" name="from_ayah" value="{{ $fromAyah }}" min="1" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">🔢 إلى آية</label>
                    <input type="number" name="to_ayah" value="{{ $toAyah }}" min="1" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">📅 تاريخ المراجعة</label>
                    <input type="date" name="date" value="{{ now()->toDateString() }}" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1.5">📝 ملاحظات</label>
                    <input type="text" name="notes" class="w-full rounded-xl border-gray-200 bg-gray-50 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 transition" placeholder="ملاحظات عامة...">
                </div>
            </div>
            <div class="flex justify-center pt-2">
                <button type="submit" class="bg-gradient-to-r from-emerald-700 to-emerald-600 text-white px-10 py-3 rounded-xl hover:from-emerald-800 hover:to-emerald-700 transition text-lg font-bold shadow-lg shadow-emerald-700/25">
                    ✨ بدء المراجعة
                </button>
            </div>
        </form>
    </div>
    @else
    <div class="flex items-center gap-3 mb-2 animate-slide-right">
        <a href="{{ route('teacher.quran-review.index') }}" class="text-emerald-600 hover:text-emerald-800 text-sm font-medium transition">
            ← العودة إلى المراجعات
        </a>
        <span class="text-gray-300">|</span>
        <span class="text-gray-500 text-sm">جلسة مراجعة جديدة</span>
    </div>

    <form id="review-form" method="POST" action="{{ route('teacher.quran-review.store') }}">
        @csrf
        <input type="hidden" name="surah_id" value="{{ $surahId }}">
        <input type="hidden" name="student_id" value="{{ $studentId }}">
        <input type="hidden" name="from_ayah" value="{{ $fromAyah }}">
        <input type="hidden" name="to_ayah" value="{{ $toAyah }}">
        <input type="hidden" name="date" value="{{ request('date', now()->toDateString()) }}">
        <input type="hidden" name="notes" value="{{ request('notes') }}">

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

        {{-- Ayahs --}}
        <div id="ayahs-container" class="space-y-4 animate-fade-in-up">
            @foreach($ayahs as $ayah)
                <div class="ayah-container" data-ayah-id="{{ $ayah->id }}">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="ayah-number">{{ $ayah->ayah_number }}</span>
                        <span class="text-xs text-gray-400">{{ optional($ayah->surah)->name_arabic }} — الآية {{ $ayah->ayah_number }}</span>
                    </div>
                    <div class="quran-text leading-loose">
                        @php $words = explode(' ', $ayah->text_simple); @endphp
                        @foreach($words as $pos => $word)
                            @if($word !== '')
                                <span class="quran-word status-correct"
                                      data-ayah-id="{{ $ayah->id }}"
                                      data-word-index="{{ $loop->index }}"
                                      data-word="{{ $word }}"
                                      data-status="correct"
                                      onclick="toggleWordError(this, event)"
                                      oncontextmenu="toggleWordError(this, event); return false;">
                                    {{ $word }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Stats Summary --}}
        <div class="mt-6 bg-white rounded-2xl shadow-sm border border-gray-200 p-8 card-hover animate-fade-in-up">
            <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                📊 ملخص المراجعة
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
                    💾 حفظ المراجعة
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
    let wordCounter = 0;

    document.querySelectorAll('.quran-word').forEach((el, index) => {
        el.dataset.globalIndex = index;
    });

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
        document.getElementById('error-popup').classList.add('hidden');
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
        if (!currentWordElement) return;
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

    updateStats();
</script>
@endpush
