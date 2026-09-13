@once
@push('scripts')
<script>
(function () {
    const previewResultLabels = {
        excellent: 'ممتاز',
        very_good: 'جيد جداً',
        good: 'جيد',
        needs_review: 'يحتاج مراجعة',
    };

    let activePreviewWord = null;
    let activePreviewPopup = null;

    function toArabicDigits(value) {
        return String(value).replace(/[0-9]/g, d => '٠١٢٣٤٥٦٧٨٩'[d]);
    }

    function resultForMastery(mastery) {
        if (mastery >= 95) return 'excellent';
        if (mastery >= 85) return 'very_good';
        if (mastery >= 70) return 'good';
        return 'needs_review';
    }

    function previewViewer() {
        return document.querySelector('[data-preview-viewer]');
    }

    function previewStats(viewer) {
        const stats = { total: 0, correct: 0, incorrect: 0, hesitation: 0, tajweed_error: 0, mastery: 100 };

        viewer.querySelectorAll('.quran-word').forEach(function (word) {
            stats.total++;
            const status = word.dataset.status || 'correct';
            if (Object.prototype.hasOwnProperty.call(stats, status)) stats[status]++;
        });

        stats.mastery = stats.total > 0 ? Math.round((stats.correct / stats.total) * 100) : 100;

        return stats;
    }

    function renderPreviewStats(viewer) {
        const stats = previewStats(viewer);

        viewer.querySelectorAll('[data-preview-stat]').forEach(function (el) {
            const key = el.dataset.previewStat;
            el.textContent = key === 'mastery' ? stats.mastery + '%' : stats[key];
        });

        const bar = viewer.querySelector('[data-preview-mastery-bar]');
        if (bar) bar.style.width = stats.mastery + '%';

        const suggested = resultForMastery(stats.mastery);
        const suggestion = viewer.querySelector('[data-preview-suggestion]');
        if (suggestion) suggestion.textContent = previewResultLabels[suggested];

        viewer.dataset.mastery = String(stats.mastery);
        viewer.dataset.suggestedResult = suggested;
    }

    function hidePreviewPopup() {
        if (activePreviewPopup) activePreviewPopup.classList.add('hidden');

        document.querySelectorAll('[data-preview-viewer] .quran-word.active-word').forEach(function (word) {
            word.classList.remove('active-word');
        });

        activePreviewWord = null;
        activePreviewPopup = null;
    }

    window.tasmeePreviewWord = function (word, event) {
        event.preventDefault();
        event.stopPropagation();

        const root = word.closest('[data-preview-root]');
        const popup = root ? root.querySelector('[data-preview-popup]') : null;
        if (!popup) return;

        activePreviewWord = word;
        activePreviewPopup = popup;
        popup.classList.remove('hidden');

        const rect = word.getBoundingClientRect();
        const popupWidth = 250;
        let left = rect.left + rect.width / 2 - popupWidth / 2;
        let top = rect.bottom + 10;

        if (left < 10) left = 10;
        if (left + popupWidth > window.innerWidth - 10) left = window.innerWidth - popupWidth - 10;
        if (top + 290 > window.innerHeight) top = rect.top - 300;
        if (top < 10) top = 10;

        popup.style.left = left + 'px';
        popup.style.top = top + 'px';

        root.querySelectorAll('.quran-word').forEach(function (w) { w.classList.remove('active-word'); });
        word.classList.add('active-word');
    };

    window.tasmeePreviewSetStatus = function (status) {
        if (!activePreviewWord) return;

        const viewer = activePreviewWord.closest('[data-preview-viewer]');
        activePreviewWord.className = 'quran-word status-' + status;
        activePreviewWord.dataset.status = status;

        hidePreviewPopup();

        if (viewer) renderPreviewStats(viewer);
    };

    window.tasmeePreviewHidePopup = hidePreviewPopup;

    window.tasmeePreviewGoTo = function (index, scroll) {
        const viewer = previewViewer();
        if (!viewer) return;

        const steps = viewer.querySelectorAll('[data-preview-step]');
        if (!steps.length || index < 0 || index >= steps.length) return;

        hidePreviewPopup();

        steps.forEach(function (step, i) { step.classList.toggle('hidden', i !== index); });

        const current = viewer.querySelector('[data-preview-current]');
        if (current) current.textContent = toArabicDigits(index + 1);

        const mushaf = viewer.querySelector('[data-preview-mushaf]');
        if (mushaf) mushaf.textContent = toArabicDigits(steps[index].dataset.page);

        const prev = viewer.querySelector('[data-preview-prev]');
        const next = viewer.querySelector('[data-preview-next]');
        if (prev) prev.disabled = index === 0;
        if (next) next.disabled = index === steps.length - 1;

        viewer.querySelectorAll('[data-preview-page-target]').forEach(function (dot) {
            const active = parseInt(dot.dataset.previewPageTarget, 10) === index;
            dot.className = 'w-8 h-8 rounded-lg text-xs font-bold transition ' +
                (active ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-emerald-50');
        });

        viewer.dataset.currentStep = String(index);

        if (scroll === false) return;

        const scrollBody = viewer.closest('[data-pages-body]');
        const nav = viewer.querySelector('[data-preview-nav]');

        if (scrollBody && scrollBody.scrollHeight > scrollBody.clientHeight && nav) {
            const top = nav.getBoundingClientRect().top - scrollBody.getBoundingClientRect().top + scrollBody.scrollTop;
            scrollBody.scrollTo({ top: Math.max(0, top - 8), behavior: 'smooth' });
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    };

    window.tasmeePreviewStep = function (delta) {
        const viewer = previewViewer();
        if (!viewer) return;

        const current = parseInt(viewer.dataset.currentStep || '0', 10);
        window.tasmeePreviewGoTo(current + delta);
    };

    function applyStoredStatuses(viewer) {
        let stored = {};

        try {
            stored = JSON.parse(viewer.dataset.previewStatuses || '{}') || {};
        } catch (e) {
            stored = {};
        }

        if (!Object.keys(stored).length) return;

        viewer.querySelectorAll('.quran-word').forEach(function (word) {
            const key = word.dataset.ayahId + ':' + word.dataset.wordIndex;
            const status = stored[key];

            if (!status) return;

            word.className = 'quran-word status-' + status;
            word.dataset.status = status;
        });
    }

    function serializeStatuses(form, viewer) {
        form.querySelectorAll('input[data-serialized-status]').forEach(function (input) { input.remove(); });

        viewer.querySelectorAll('.quran-word').forEach(function (word) {
            const status = word.dataset.status || 'correct';
            if (status === 'correct') return;

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'word_statuses[' + word.dataset.ayahId + ':' + word.dataset.wordIndex + ']';
            input.value = status;
            input.dataset.serializedStatus = '1';
            form.appendChild(input);
        });
    }

    document.addEventListener('click', function (event) {
        if (!event.target.closest('[data-preview-popup]') && !event.target.closest('[data-preview-root] .quran-word')) {
            hidePreviewPopup();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            if (activePreviewWord) {
                event.preventDefault();
                event.stopImmediatePropagation();
                hidePreviewPopup();
            }
            return;
        }

        if (!activePreviewWord && (event.key === 'PageDown' || event.key === 'PageUp')) {
            const viewer = previewViewer();
            if (viewer && viewer.querySelector('[data-preview-nav]')) {
                event.preventDefault();
                window.tasmeePreviewStep(event.key === 'PageDown' ? 1 : -1);
            }
            return;
        }

        if (!activePreviewWord) return;

        const keys = {
            '1': 'correct',
            '2': 'incorrect',
            '3': 'hesitation',
            '4': 'tajweed_error',
            '5': 'added',
            '6': 'forgotten',
        };

        if (keys[event.key]) {
            event.preventDefault();
            window.tasmeePreviewSetStatus(keys[event.key]);
        }
    });

    const viewer = previewViewer();
    if (viewer) {
        applyStoredStatuses(viewer);
        renderPreviewStats(viewer);
        window.tasmeePreviewGoTo(0, false);

        const form = viewer.closest('form[data-quran-preview-form]');
        if (form) {
            form.addEventListener('submit', function () {
                serializeStatuses(form, viewer);

                const select = form.querySelector('[name="result"]');
                if (select && !select.value && viewer.dataset.suggestedResult) {
                    select.value = viewer.dataset.suggestedResult;
                }
            });
        }
    }
})();
</script>
@endpush
@endonce
