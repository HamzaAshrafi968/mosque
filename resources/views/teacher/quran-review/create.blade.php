@extends('layouts.app')

@section('title', 'مراجعة القرآن الكريم')

@push('styles')
<style>
    .quran-word {
        display: inline-block;
        padding: 4px 8px;
        margin: 3px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 1.5rem;
        font-family: 'Traditional Arabic', 'Scheherazade New', 'Amiri', serif;
        transition: all 0.2s ease;
        user-select: none;
        line-height: 2.2;
    }
    .quran-word:hover {
        transform: scale(1.05);
        box-shadow: 0 2px 8px rgba(0,0,0,0.15);
    }
    .quran-word.status-unreviewed { background-color: #e5e7eb; color: #6b7280; }
    .quran-word.status-correct { background-color: #bbf7d0; color: #166534; }
    .quran-word.status-incorrect { background-color: #fecaca; color: #991b1b; }
    .quran-word.status-hesitation { background-color: #fef08a; color: #854d0e; }
    .quran-word.status-tajweed_error { background-color: #bfdbfe; color: #1e40af; }
    .quran-word.status-added { background-color: #fbcfe8; color: #9d174d; }
    .quran-word.status-forgotten { background-color: #fed7aa; color: #9a3412; }
    .quran-word.active-word {
        outline: 3px solid #7c3aed;
        outline-offset: 2px;
    }
    .error-popup {
        position: absolute;
        z-index: 50;
        background: white;
        border: 1px solid #d1d5db;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        padding: 10px;
        min-width: 200px;
    }
    .status-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 12px;
        background: white;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    }
    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.85rem;
    }
    .legend-dot {
        width: 14px;
        height: 14px;
        border-radius: 4px;
    }
    .ayah-container {
        background: white;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 12px;
        border: 1px solid #e5e7eb;
        text-align: right;
        direction: rtl;
        line-height: 2.8;
    }
    .ayah-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        background: #10b981;
        color: white;
        border-radius: 50%;
        font-size: 0.85rem;
        margin-left: 8px;
        vertical-align: middle;
    }
    .review-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 10px;
    }
    .stat-card {
        background: white;
        border-radius: 10px;
        padding: 12px;
        text-align: center;
        border: 1px solid #e5e7eb;
    }
    .stat-value {
        font-size: 1.5rem;
        font-weight: bold;
    }
</style>
@endpush

@section('content')
<div class="space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <a href="{{ route('teacher.quran-review.index') }}" class="text-emerald-700 hover:underline text-sm">&larr; العودة إلى المراجعات</a>
        </div>
    </div>

    @if(!$ayahs->count())
    {{-- Step 1: Select Surah and Student --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">إعداد جلسة مراجعة جديدة</h2>
        <form method="GET" action="{{ route('teacher.quran-review.create') }}" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اختر الطالب</label>
                    <select name="student_id" required class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">-- اختر الطالب --</option>
                        @foreach($students as $student)
                            <option value="{{ $student->id }}" {{ $studentId == $student->id ? 'selected' : '' }}>
                                {{ $student->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">اختر السورة</label>
                    <select name="surah_id" required class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                        <option value="">-- اختر السورة --</option>
                        @foreach($surahs as $s)
                            <option value="{{ $s->id }}" {{ $surahId == $s->id ? 'selected' : '' }}>
                                {{ $s->name_arabic }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">من آية</label>
                    <input type="number" name="from_ayah" value="{{ $fromAyah }}" min="1" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">إلى آية</label>
                    <input type="number" name="to_ayah" value="{{ $toAyah }}" min="1" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ المراجعة</label>
                    <input type="date" name="date" value="{{ now()->toDateString() }}" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                    <input type="text" name="notes" class="w-full rounded-lg border-gray-300 focus:border-emerald-500 focus:ring-emerald-500" placeholder="ملاحظات عامة...">
                </div>
            </div>
            <div class="text-left">
                <button type="submit" class="bg-emerald-700 text-white px-6 py-2.5 rounded-lg hover:bg-emerald-800 transition">
                    بدء المراجعة
                </button>
            </div>
        </form>
    </div>
    @else
    {{-- Step 2: Interactive Review --}}
    <form id="review-form" method="POST" action="{{ route('teacher.quran-review.store') }}">
        @csrf
        <input type="hidden" name="surah_id" value="{{ $surahId }}">
        <input type="hidden" name="student_id" value="{{ $studentId }}">
        <input type="hidden" name="from_ayah" value="{{ $fromAyah }}">
        <input type="hidden" name="to_ayah" value="{{ $toAyah }}">
        <input type="hidden" name="date" value="{{ request('date', now()->toDateString()) }}">
        <input type="hidden" name="notes" value="{{ request('notes') }}">

        {{-- Legend --}}
        <div class="status-legend mb-4">
            <span class="text-sm font-bold text-gray-600">دليل الألوان:</span>
            <span class="legend-item"><span class="legend-dot status-unreviewed"></span> لم تُراجع</span>
            <span class="legend-item"><span class="legend-dot status-correct"></span> صحيحة</span>
            <span class="legend-item"><span class="legend-dot status-incorrect"></span> خطأ نطق</span>
            <span class="legend-item"><span class="legend-dot status-hesitation"></span> تردد</span>
            <span class="legend-item"><span class="legend-dot status-tajweed_error"></span> خطأ تجويد</span>
            <span class="legend-item"><span class="legend-dot status-added"></span> زيادة</span>
            <span class="legend-item"><span class="legend-dot status-forgotten"></span> نسيان</span>
        </div>

        {{-- Ayahs --}}
        <div id="ayahs-container" class="space-y-3">
            @foreach($ayahs as $ayah)
                <div class="ayah-container" data-ayah-id="{{ $ayah->id }}">
                    <span class="ayah-number">{{ $ayah->ayah_number }}</span>
                    @php $words = explode(' ', $ayah->text_simple); $globalIndex = 0; @endphp
                    @foreach($words as $pos => $word)
                        @if($word !== '')
                            <span class="quran-word status-unreviewed"
                                  data-ayah-id="{{ $ayah->id }}"
                                  data-word-index="{{ $loop->index }}"
                                  data-global-index="{{ $globalIndex }}"
                                  data-word="{{ $word }}"
                                  onclick="toggleWordError(this, event)"
                                  oncontextmenu="toggleWordError(this, event); return false;">
                                {{ $word }}
                            </span>
                            @php $globalIndex++; @endphp
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>

        {{-- Stats Summary --}}
        <div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-3">ملخص المراجعة</h3>
            <div class="review-stats mb-4">
                <div class="stat-card">
                    <div class="text-gray-500 text-sm">الإجمالي</div>
                    <div class="stat-value text-gray-700" id="stat-total">0</div>
                </div>
                <div class="stat-card">
                    <div class="text-gray-500 text-sm">صحيحة</div>
                    <div class="stat-value text-green-600" id="stat-correct">0</div>
                </div>
                <div class="stat-card">
                    <div class="text-gray-500 text-sm">أخطاء النطق</div>
                    <div class="stat-value text-red-600" id="stat-incorrect">0</div>
                </div>
                <div class="stat-card">
                    <div class="text-gray-500 text-sm">تردد</div>
                    <div class="stat-value text-yellow-600" id="stat-hesitation">0</div>
                </div>
                <div class="stat-card">
                    <div class="text-gray-500 text-sm">أخطاء التجويد</div>
                    <div class="stat-value text-blue-600" id="stat-tajweed_error">0</div>
                </div>
                <div class="stat-card">
                    <div class="text-gray-500 text-sm">نسبة الإتقان</div>
                    <div class="stat-value text-emerald-600" id="stat-mastery">100%</div>
                </div>
            </div>
            <button type="submit" class="bg-emerald-700 text-white px-8 py-3 rounded-lg hover:bg-emerald-800 transition text-lg font-bold">
                حفظ المراجعة
            </button>
        </div>
    </form>

    {{-- Error type popup (hidden by default) --}}
    <div id="error-popup" class="error-popup hidden">
        <div class="text-sm font-bold text-gray-700 mb-2 border-b pb-2">اختر نوع الخطأ:</div>
        <div class="grid grid-cols-2 gap-1">
            <button onclick="setWordStatus('correct')" class="px-3 py-2 rounded-lg text-sm bg-green-100 hover:bg-green-200 text-green-800 transition">✅ صحيحة</button>
            <button onclick="setWordStatus('incorrect')" class="px-3 py-2 rounded-lg text-sm bg-red-100 hover:bg-red-200 text-red-800 transition">❌ خطأ نطق</button>
            <button onclick="setWordStatus('hesitation')" class="px-3 py-2 rounded-lg text-sm bg-yellow-100 hover:bg-yellow-200 text-yellow-800 transition">🟡 تردد</button>
            <button onclick="setWordStatus('tajweed_error')" class="px-3 py-2 rounded-lg text-sm bg-blue-100 hover:bg-blue-200 text-blue-800 transition">🔵 خطأ تجويد</button>
            <button onclick="setWordStatus('added')" class="px-3 py-2 rounded-lg text-sm bg-pink-100 hover:bg-pink-200 text-pink-800 transition">➕ زيادة</button>
            <button onclick="setWordStatus('forgotten')" class="px-3 py-2 rounded-lg text-sm bg-orange-100 hover:bg-orange-200 text-orange-800 transition">➖ نسيان</button>
        </div>
        <button onclick="hidePopup()" class="mt-2 w-full text-center text-xs text-gray-400 hover:text-gray-600 py-1">إلغاء</button>
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
        const popupWidth = 220;
        let left = rect.left + window.scrollX;
        let top = rect.bottom + window.scrollY + 5;

        if (left + popupWidth > window.innerWidth) {
            left = window.innerWidth - popupWidth - 10;
        }
        if (top + 220 > window.innerHeight + window.scrollY) {
            top = rect.top + window.scrollY - 225;
        }

        popup.style.left = left + 'px';
        popup.style.top = top + 'px';

        document.querySelectorAll('.quran-word').forEach(w => w.classList.remove('active-word'));
        el.classList.add('active-word');
    }

    function setWordStatus(status) {
        if (!currentWordElement) return;

        currentWordElement.className = 'quran-word status-' + status;
        currentWordElement.dataset.status = status;

        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'word_statuses[]';
        hiddenInput.value = status;
        hiddenInput.dataset.wordIndex = currentWordElement.dataset.globalIndex;

        const existingInput = document.querySelector(`input[name="word_statuses[]"][data-word-index="${currentWordElement.dataset.globalIndex}"]`);
        if (existingInput) {
            existingInput.value = status;
        } else {
            const form = document.getElementById('review-form');
            const container = document.getElementById('word-statuses-container');
            if (!container) {
                const div = document.createElement('div');
                div.id = 'word-statuses-container';
                form.appendChild(div);
                div.appendChild(hiddenInput);
            } else {
                container.appendChild(hiddenInput);
            }
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
            const status = w.dataset.status || w.className.match(/status-(\w+)/)?.[1] || 'unreviewed';
            if (status !== 'unreviewed') total++;
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

    document.getElementById('review-form').addEventListener('submit', function(e) {
        const allWords = document.querySelectorAll('.quran-word');
        allWords.forEach(w => {
            const status = w.dataset.status || w.className.match(/status-(\w+)/)?.[1] || 'unreviewed';
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
</script>
@endpush
