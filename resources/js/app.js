import './bootstrap';

/* ============================================================
   1) القائمة الجانبية
============================================================ */
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebar-overlay');
const toggleBtn = document.getElementById('sidebar-toggle');
const closeBtn = document.getElementById('sidebar-close');
const collapseBtn = document.getElementById('sidebar-collapse');
const COLLAPSE_KEY = 'mosque:sidebar-collapsed';

function isDesktop() {
    return window.innerWidth >= 1024;
}

function openSidebar() {
    if (!sidebar || !overlay) return;
    sidebar.classList.remove('translate-x-full');
    sidebar.classList.add('translate-x-0');
    overlay.classList.remove('hidden');
    overlay.classList.add('animate-fade-in');
    document.body.classList.add('overflow-hidden');
}

function closeSidebar() {
    if (!sidebar || !overlay) return;
    sidebar.classList.add('translate-x-full');
    sidebar.classList.remove('translate-x-0');
    overlay.classList.add('hidden');
    overlay.classList.remove('animate-fade-in');
    document.body.classList.remove('overflow-hidden');
}

toggleBtn?.addEventListener('click', openSidebar);
closeBtn?.addEventListener('click', closeSidebar);
overlay?.addEventListener('click', closeSidebar);

window.addEventListener('resize', () => {
    if (isDesktop()) {
        sidebar?.classList.remove('translate-x-full');
        sidebar?.classList.remove('translate-x-0');
        overlay?.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        setSidebarCollapsed(readCollapsedPreference(), false);
    }
});

sidebar?.addEventListener('click', (event) => {
    if (isDesktop()) return;
    const link = event.target.closest('a[href]');
    if (link && !link.getAttribute('href')?.startsWith('#')) {
        closeSidebar();
    }
});

/* --- طيّ القائمة الجانبية (سطح المكتب) --- */
function readCollapsedPreference() {
    try {
        return localStorage.getItem(COLLAPSE_KEY) === '1';
    } catch (error) {
        return false;
    }
}

function setSidebarCollapsed(collapsed, persist = true) {
    document.body.classList.toggle('sidebar-collapsed', collapsed);

    if (persist) {
        try {
            localStorage.setItem(COLLAPSE_KEY, collapsed ? '1' : '0');
        } catch (error) {
            /* تجاهل */
        }
    }

    const label = collapsed ? 'توسيع القائمة' : 'طيّ القائمة';
    collapseBtn?.setAttribute('title', label);
    collapseBtn?.setAttribute('aria-label', label);

    document.querySelectorAll('#sidebar [data-label]').forEach((el) => {
        if (collapsed) el.setAttribute('title', el.dataset.label);
        else el.removeAttribute('title');
    });
}

function initSidebarCollapse() {
    if (isDesktop() && readCollapsedPreference()) {
        setSidebarCollapsed(true, false);
    }

    collapseBtn?.addEventListener('click', () => {
        setSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
    });
}

/* --- المجموعات المنسدلة داخل القائمة --- */
function setGroupOpen(group, open) {
    group.toggleAttribute('data-open', open);
    group.querySelector('[data-nav-group-toggle]')?.setAttribute('aria-expanded', open ? 'true' : 'false');
}

function initSidebarGroups() {
    document.querySelectorAll('#sidebar [data-nav-group]').forEach((group) => {
        const toggle = group.querySelector('[data-nav-group-toggle]');
        if (!toggle) return;

        toggle.addEventListener('click', () => {
            if (document.body.classList.contains('sidebar-collapsed') && isDesktop()) {
                setSidebarCollapsed(false);
                setGroupOpen(group, true);
                return;
            }

            const willOpen = !group.hasAttribute('data-open');

            group.parentElement
                ?.querySelectorAll(':scope > [data-nav-group][data-open]')
                .forEach((sibling) => {
                    if (sibling !== group) setGroupOpen(sibling, false);
                });

            setGroupOpen(group, willOpen);
        });
    });
}

/* ============================================================
   2) الكشف عن العناصر أثناء التمرير (Reveal)
============================================================ */
function revealOnScroll() {
    const items = document.querySelectorAll('.reveal:not(.is-visible)');
    if (!items.length) return;

    if (!('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.08, rootMargin: '0px 0px -24px 0px' });

    items.forEach((el) => observer.observe(el));
}

/* ============================================================
   3) عدّادات الأرقام المتحركة
============================================================ */
function animateCountUp(el, to, duration = 1300) {
    const decimals = String(to).includes('.') ? String(to).split('.')[1].length : 0;
    const start = performance.now();

    function frame(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = (to * eased).toLocaleString('en-US', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
        if (progress < 1) requestAnimationFrame(frame);
    }
    requestAnimationFrame(frame);
}

function initCounters() {
    const counters = document.querySelectorAll('[data-count-up][data-to]');
    if (!counters.length) return;

    const run = (el) => {
        if (el.dataset.done === '1') return;
        el.dataset.done = '1';
        const to = parseFloat(el.dataset.to);
        if (Number.isFinite(to)) animateCountUp(el, to);
        else el.textContent = el.dataset.to;
    };

    counters.forEach((el) => {
        if (el.getBoundingClientRect().top < window.innerHeight) {
            setTimeout(() => run(el), 150);
            return;
        }
    });

    if ('IntersectionObserver' in window) {
        const obs = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    run(entry.target);
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });
        counters.forEach((el) => obs.observe(el));
    }
}

/* ============================================================
   4) رسائل الفلاش (Toast) — إغلاق تلقائي مع شريط تقدّم
============================================================ */
function initFlashToasts() {
    const HIDE_AFTER_MS = 6000;

    document.querySelectorAll('[data-flash]').forEach((toast) => {
        const bar = toast.querySelector('[data-flash-bar]');
        const closeBtn = toast.querySelector('[data-flash-close]');
        let timer = null;

        const dismiss = () => {
            if (timer) clearTimeout(timer);
            toast.style.transition = 'opacity .45s ease, transform .45s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-12px) scale(.97)';
            setTimeout(() => toast.remove(), 460);
        };

        if (bar) {
            bar.style.animationDuration = `${HIDE_AFTER_MS}ms`;
            bar.addEventListener('animationend', dismiss, { once: true });
        }
        closeBtn?.addEventListener('click', dismiss);
        timer = setTimeout(dismiss, HIDE_AFTER_MS + 200);
    });
}

/* ============================================================
   5) إظهار / إخفاء كلمة المرور
============================================================ */
function initPasswordToggles() {
    document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const container = btn.closest('.relative') ?? btn.parentElement;
            const input = container.querySelector('input[type="password"], input[type="text"]');
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.querySelector('[data-eye-icon]')?.classList.toggle('hidden', show);
            btn.querySelector('[data-eye-off-icon]')?.classList.toggle('hidden', !show);
            btn.setAttribute('aria-label', show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
        });
    });
}

/* ============================================================
   6) معاينة الصور الشخصية قبل الرفع
============================================================ */
function initPhotoPreviews() {
    document.querySelectorAll('[data-photo-input]').forEach((input) => {
        const field = input.closest('[data-photo-field]');
        const preview = field?.querySelector('[data-photo-preview]');
        const empty = field?.querySelector('[data-photo-empty]');
        const remove = field?.querySelector('[data-photo-remove]');
        const hasPhoto = Boolean(preview?.getAttribute('src'));

        const sync = () => {
            const removed = remove?.checked ?? false;
            preview?.classList.toggle('hidden', removed || !preview.getAttribute('src'));
            empty?.classList.toggle('hidden', !removed && Boolean(preview?.getAttribute('src')));
        };

        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) return;
            if (remove) remove.checked = false;
            const reader = new FileReader();
            reader.onload = (e) => {
                preview.src = e.target.result;
                sync();
            };
            reader.readAsDataURL(file);
        });

        remove?.addEventListener('change', sync);
        sync();
    });
}

/* ============================================================
   7) زر "إغلاق" عام (أي زر يحمل class btn-dismiss-js يخفي أقرب عنصر)
============================================================ */
document.querySelectorAll('[data-dismiss-parent]').forEach((btn) => {
    btn.addEventListener('click', () => btn.closest(btn.dataset.dismissParent)?.remove());
});

/* ============================================================
   7.1) تفعيل حساب البوابة عند التعديل فقط (لا يتغيّر الحساب افتراضياً)
============================================================ */
function initPortalAccountToggles() {
    document.querySelectorAll('[data-portal-account]').forEach((section) => {
        const toggle = section.querySelector('[data-portal-account-toggle]');
        const fields = section.querySelector('[data-portal-account-fields]');
        if (!toggle || !fields) return;

        const sync = () => {
            fields.querySelectorAll('input').forEach((input) => {
                input.disabled = !toggle.checked;
            });
            fields.classList.toggle('opacity-50', !toggle.checked);
        };

        toggle.addEventListener('change', sync);
        sync();
    });
}

/* ============================================================
   8) مسجّل الرسائل الصوتية (مثل واتساب)
============================================================ */
function initVoiceRecorders() {
    const MAX_SECONDS = 15 * 60;

    const pickMimeType = () => {
        if (typeof MediaRecorder === 'undefined' || typeof MediaRecorder.isTypeSupported !== 'function') {
            return '';
        }
        const candidates = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/ogg;codecs=opus',
            'audio/ogg',
            'audio/mp4',
        ];
        return candidates.find((type) => MediaRecorder.isTypeSupported(type)) ?? '';
    };

    const extensionFor = (type) => {
        const base = (type || '').split(';')[0];
        if (base.includes('ogg')) return 'ogg';
        if (base.includes('mp4')) return 'm4a';
        if (base.includes('mpeg')) return 'mp3';
        return 'webm';
    };

    const formatTime = (seconds) => {
        const minutes = String(Math.floor(seconds / 60)).padStart(2, '0');
        const rest = String(seconds % 60).padStart(2, '0');
        return `${minutes}:${rest}`;
    };

    const micErrorMessage = (error) => {
        switch (error?.name) {
            case 'NotAllowedError':
            case 'SecurityError':
                return 'تم رفض الوصول إلى الميكروفون. اسمح للمتصفح باستخدام الميكروفون ثم حاول مرة أخرى.';
            case 'NotFoundError':
            case 'DevicesNotFoundError':
                return 'لم يتم العثور على ميكروفون متصل بالجهاز.';
            case 'NotReadableError':
            case 'TrackStartError':
                return 'الميكروفون مستخدم من قِبل تطبيق آخر. أغلق التطبيقات الأخرى وحاول مرة أخرى.';
            default:
                return 'تعذّر بدء التسجيل. تأكد من منح إذن الميكروفون وأن الموقع يعمل عبر اتصال آمن (HTTPS).';
        }
    };

    document.querySelectorAll('[data-voice-recorder]').forEach((root) => {
        const input = root.querySelector('[data-voice-input]');
        const startBtn = root.querySelector('[data-voice-start]');
        const stopBtn = root.querySelector('[data-voice-stop]');
        const cancelBtn = root.querySelector('[data-voice-cancel]');
        const removeBtn = root.querySelector('[data-voice-remove]');
        const recordingPanel = root.querySelector('[data-voice-recording]');
        const previewPanel = root.querySelector('[data-voice-preview]');
        const timerEl = root.querySelector('[data-voice-timer]');
        const durationEl = root.querySelector('[data-voice-duration]');
        const audioEl = root.querySelector('[data-voice-audio]');
        const errorEl = root.querySelector('[data-voice-error]');
        const form = root.closest('form');

        if (!input || !startBtn || typeof MediaRecorder === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
            if (startBtn) {
                startBtn.disabled = true;
                startBtn.title = 'التسجيل الصوتي غير مدعوم في هذا المتصفح';
            }
            return;
        }

        let recorder = null;
        let stream = null;
        let chunks = [];
        let seconds = 0;
        let ticker = null;
        let objectUrl = null;
        let recorderOwnsInput = false;
        let state = 'idle';
        let submitPending = false;

        const showError = (message) => {
            if (!errorEl) return;
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
        };

        const clearError = () => {
            if (!errorEl) return;
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
        };

        const setState = (next) => {
            state = next;
            startBtn.disabled = next !== 'idle';
            recordingPanel?.classList.toggle('hidden', next !== 'recording');
            previewPanel?.classList.toggle('hidden', next !== 'preview');
            input.disabled = next === 'recording';
        };

        const stopTicker = () => {
            if (ticker) {
                clearInterval(ticker);
                ticker = null;
            }
        };

        const releaseStream = () => {
            stream?.getTracks().forEach((track) => track.stop());
            stream = null;
        };

        const resetRecorder = () => {
            stopTicker();
            releaseStream();
            recorder = null;
            chunks = [];
            seconds = 0;
            if (timerEl) timerEl.textContent = '00:00';
        };

        const clearPreview = () => {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }
            if (audioEl) {
                audioEl.pause();
                audioEl.removeAttribute('src');
                audioEl.load();
            }
            if (durationEl) durationEl.textContent = '';
        };

        const discardRecording = () => {
            clearPreview();
            if (recorderOwnsInput) {
                input.value = '';
                recorderOwnsInput = false;
            }
        };

        const attachFile = (file) => {
            try {
                const transfer = new DataTransfer();
                transfer.items.add(file);
                input.files = transfer.files;
                recorderOwnsInput = true;
                return true;
            } catch (error) {
                showError('تعذّر إرفاق التسجيل في هذا المتصفح. استخدم خيار رفع ملف صوتي بدلًا من ذلك.');
                return false;
            }
        };

        const buildRecording = () => {
            const type = (recorder?.mimeType || chunks[0]?.type || 'audio/webm').split(';')[0] || 'audio/webm';
            const blob = new Blob(chunks, { type });
            const name = `voice-message-${Date.now()}.${extensionFor(type)}`;

            return new File([blob], name, { type });
        };

        const finishRecording = (discard = false) => {
            const built = discard ? null : buildRecording();
            const wasSubmitPending = submitPending;
            const duration = seconds;
            resetRecorder();

            if (!built) {
                setState('idle');
                return;
            }

            if (!attachFile(built)) {
                setState('idle');
                return;
            }

            if (objectUrl) URL.revokeObjectURL(objectUrl);
            objectUrl = URL.createObjectURL(built);
            if (audioEl) audioEl.src = objectUrl;
            if (durationEl) durationEl.textContent = formatTime(duration);
            setState('preview');
            clearError();

            if (wasSubmitPending) {
                submitPending = false;
                form?.submit();
            }
        };

        const stopRecording = () => {
            if (state !== 'recording' || !recorder) return;
            state = 'processing';
            startBtn.disabled = true;
            if (stopBtn) stopBtn.disabled = true;
            if (cancelBtn) cancelBtn.disabled = true;
            recorder.stop();
        };

        const cancelRecording = () => {
            if (state !== 'recording' || !recorder) return;
            state = 'cancelling';
            recorder.stop();
        };

        startBtn.addEventListener('click', async () => {
            if (state !== 'idle') return;
            clearError();

            try {
                stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            } catch (error) {
                showError(micErrorMessage(error));
                return;
            }

            const mimeType = pickMimeType();
            try {
                recorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
            } catch (error) {
                try {
                    recorder = new MediaRecorder(stream);
                } catch (fallbackError) {
                    releaseStream();
                    showError('تعذّر بدء التسجيل في هذا المتصفح.');
                    return;
                }
            }

            chunks = [];
            seconds = 0;
            if (timerEl) timerEl.textContent = '00:00';

            recorder.addEventListener('dataavailable', (event) => {
                if (event.data?.size) chunks.push(event.data);
            });

            recorder.addEventListener('stop', () => {
                const discard = state === 'cancelling';
                if (stopBtn) stopBtn.disabled = false;
                if (cancelBtn) cancelBtn.disabled = false;
                finishRecording(discard);
            });

            recorder.addEventListener('error', () => {
                showError('حدث خطأ أثناء التسجيل. حاول مرة أخرى.');
                finishRecording(true);
            });

            recorder.start();
            setState('recording');

            ticker = setInterval(() => {
                seconds += 1;
                if (timerEl) timerEl.textContent = formatTime(seconds);
                if (seconds >= MAX_SECONDS) {
                    stopRecording();
                }
            }, 1000);
        });

        stopBtn?.addEventListener('click', stopRecording);
        cancelBtn?.addEventListener('click', cancelRecording);

        removeBtn?.addEventListener('click', () => {
            discardRecording();
            setState('idle');
            clearError();
        });

        input.addEventListener('change', () => {
            if (state === 'recording') return;
            if (!input.files?.length) {
                if (recorderOwnsInput) discardRecording();
                return;
            }
            if (recorderOwnsInput) {
                recorderOwnsInput = false;
                clearPreview();
            }
            setState('idle');
            clearError();
        });

        form?.addEventListener('submit', (event) => {
            if (state === 'recording') {
                event.preventDefault();
                submitPending = true;
                stopRecording();
            } else if (state === 'processing') {
                event.preventDefault();
                submitPending = true;
            }
        });
    });
}

/* ============================================================
   9) منتقي البحث (أولياء الأمور / الطلاب) + إضافة سريعة
============================================================ */
function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function initSearchPickers() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const DELAY_MS = 250;

    document.querySelectorAll('[data-search-picker]').forEach((root) => {
        const url = root.dataset.searchUrl;
        const input = root.querySelector('[data-picker-input]');
        const results = root.querySelector('[data-picker-results]');
        const selected = root.querySelector('[data-picker-selected]');
        const template = root.querySelector('template[data-picker-template]');
        const emptyState = root.querySelector('[data-picker-empty]');
        const emptyLabel = root.dataset.emptyLabel || 'لا توجد نتائج مطابقة';
        const param = root.dataset.param || 'guardian_ids';

        if (!url || !input || !results || !selected) return;

        let timer = null;
        let controller = null;

        const selectedIds = () => Array.from(selected.querySelectorAll('[data-id]')).map((el) => el.dataset.id);

        const syncEmptyState = () => {
            if (!emptyState) return;
            emptyState.classList.toggle('hidden', selected.querySelector('[data-id]') !== null);
        };

        const hideResults = () => {
            results.classList.add('hidden');
            results.innerHTML = '';
        };

        const showResultsMessage = (message) => {
            results.innerHTML = '';
            const item = document.createElement('div');
            item.className = 'px-3 py-2 text-sm text-gray-400';
            item.textContent = message;
            results.appendChild(item);
            results.classList.remove('hidden');
        };

        const buildChip = (item) => {
            const chip = document.createElement('span');
            chip.dataset.id = item.id;
            chip.className = 'inline-flex items-center gap-2 bg-emerald-50 text-emerald-900 border border-emerald-200 rounded-full ps-3 pe-1.5 py-1 text-sm font-medium';

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = `${param}[]`;
            hidden.value = item.id;
            chip.appendChild(hidden);

            const name = document.createElement('span');
            name.textContent = item.name;
            chip.appendChild(name);

            if (item.meta) {
                const meta = document.createElement('span');
                meta.className = 'text-[11px] text-emerald-700/70';
                meta.dir = 'ltr';
                meta.textContent = item.meta;
                chip.appendChild(meta);
            }

            const remove = document.createElement('button');
            remove.type = 'button';
            remove.dataset.pickerRemove = '';
            remove.className = 'w-5 h-5 grid place-items-center rounded-full text-emerald-700 hover:bg-emerald-200/70 hover:text-red-700 transition';
            remove.setAttribute('aria-label', 'إزالة');
            remove.textContent = '×';
            chip.appendChild(remove);

            return chip;
        };

        const addItem = (item) => {
            if (!item?.id || selectedIds().includes(String(item.id))) {
                hideResults();
                return;
            }

            if (template) {
                selected.insertAdjacentHTML('beforeend', template.innerHTML
                    .replaceAll('__ID__', escapeHtml(item.id))
                    .replaceAll('__NAME__', escapeHtml(item.name))
                    .replaceAll('__META__', escapeHtml(item.meta || '')));
            } else {
                selected.appendChild(buildChip(item));
            }

            input.value = '';
            hideResults();
            syncEmptyState();
            input.focus();
        };

        const renderResults = (items) => {
            results.innerHTML = '';

            if (!items.length) {
                showResultsMessage(emptyLabel);
                return;
            }

            items.forEach((item) => {
                const option = document.createElement('button');
                option.type = 'button';
                option.className = 'w-full flex items-center gap-2 px-3 py-2 text-right text-sm hover:bg-emerald-50 transition';

                const name = document.createElement('span');
                name.className = 'font-medium text-gray-800';
                name.textContent = item.name;
                option.appendChild(name);

                if (item.meta) {
                    const meta = document.createElement('span');
                    meta.className = 'ms-auto text-xs text-gray-400';
                    meta.dir = 'ltr';
                    meta.textContent = item.meta;
                    option.appendChild(meta);
                }

                option.addEventListener('click', () => addItem(item));
                results.appendChild(option);
            });

            results.classList.remove('hidden');
        };

        const runSearch = async () => {
            const term = input.value.trim();

            if (term === '') {
                hideResults();
                return;
            }

            controller?.abort();
            controller = new AbortController();
            showResultsMessage('جارٍ البحث...');

            try {
                const params = new URLSearchParams({ q: term });
                selectedIds().forEach((id) => params.append('exclude[]', id));

                const response = await fetch(`${url}?${params.toString()}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: controller.signal,
                });

                if (!response.ok) {
                    hideResults();
                    return;
                }

                const payload = await response.json();
                renderResults(payload.results ?? []);
            } catch (error) {
                if (error?.name !== 'AbortError') hideResults();
            }
        };

        selected.addEventListener('click', (event) => {
            const button = event.target.closest('[data-picker-remove]');
            if (!button) return;
            button.closest('[data-id]')?.remove();
            syncEmptyState();
        });

        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(runSearch, DELAY_MS);
        });

        input.addEventListener('focus', () => {
            if (input.value.trim() !== '') runSearch();
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                hideResults();
                input.blur();
            }
        });

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) hideResults();
        });

        const quickUrl = root.dataset.quickStoreUrl;
        const quickForm = root.querySelector('[data-picker-quick-form]');
        const quickName = root.querySelector('[data-picker-quick-name]');
        const quickPhone = root.querySelector('[data-picker-quick-phone]');
        const quickError = root.querySelector('[data-picker-quick-error]');

        if (quickUrl && quickForm) {
            const toggle = root.querySelector('[data-picker-quick-toggle]');
            const cancel = root.querySelector('[data-picker-quick-cancel]');
            const submit = root.querySelector('[data-picker-quick-submit]');

            const showQuickError = (message) => {
                if (!quickError) return;
                quickError.textContent = message;
                quickError.classList.toggle('hidden', !message);
            };

            const closeQuickForm = () => {
                quickForm.classList.add('hidden');
                if (quickName) quickName.value = '';
                if (quickPhone) quickPhone.value = '';
                showQuickError('');
            };

            toggle?.addEventListener('click', () => {
                quickForm.classList.toggle('hidden');
                if (!quickForm.classList.contains('hidden')) quickName?.focus();
            });

            cancel?.addEventListener('click', closeQuickForm);

            submit?.addEventListener('click', async () => {
                const name = quickName?.value.trim() ?? '';

                if (name === '') {
                    showQuickError('اكتب اسم ولي الأمر أولاً');
                    return;
                }

                submit.disabled = true;
                showQuickError('');

                try {
                    const response = await fetch(quickUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
                        },
                        body: JSON.stringify({ name, phone: quickPhone?.value.trim() || null }),
                    });

                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        showQuickError(payload?.errors?.name?.[0] || payload?.message || 'تعذّرت الإضافة، حاول مرة أخرى.');
                        return;
                    }

                    addItem(payload.result);
                    closeQuickForm();
                } catch (error) {
                    showQuickError('تعذّر الاتصال بالخادم، حاول مرة أخرى.');
                } finally {
                    submit.disabled = false;
                }
            });
        }

        syncEmptyState();
    });
}

/* ============================================================
   مؤقت الامتحان — عدّ تنازلي + تسليم تلقائي عند الصفر
=========================================================== */
function initExamTimers() {
    document.querySelectorAll('[data-exam-timer]').forEach((element) => {
        const form = document.querySelector(element.dataset.submitForm || '');
        const raw = (element.dataset.remaining || '').trim();

        if (!form || raw === '') {
            element.textContent = 'بدون وقت';
            return;
        }

        let remaining = parseInt(raw, 10);

        if (Number.isNaN(remaining)) {
            element.textContent = 'بدون وقت';
            return;
        }

        const render = () => {
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            element.textContent = minutes + ':' + String(seconds).padStart(2, '0');
            element.classList.toggle('text-red-600', remaining <= 60);
        };

        render();

        const interval = setInterval(() => {
            remaining -= 1;

            if (remaining <= 0) {
                clearInterval(interval);
                element.textContent = '0:00';
                form.submit();
                return;
            }

            render();
        }, 1000);
    });
}

/* ============================================================
   منشئ أسئلة الاختبار — أقسام متعددة الأنواع + عدّاد العلامات
   (مشترك بين صفحة إنشاء الاختبار وصفحة الامتحان)
============================================================ */
function initExamQuestionBuilders() {
    document.querySelectorAll('[data-exam-question-builder]').forEach((builder) => {
        const sectionsContainer = builder.querySelector('[data-builder-sections]');
        const rowsContainer = builder.querySelector('[data-builder-rows]');
        const generateButton = builder.querySelector('[data-builder-generate]');
        const addSectionButton = builder.querySelector('[data-builder-add-section]');
        const sumEl = builder.querySelector('[data-builder-marks-sum]');
        const targetEl = builder.querySelector('[data-builder-marks-target]');
        const form = builder.closest('form');

        if (!sectionsContainer || !rowsContainer || !generateButton || !addSectionButton) {
            return;
        }

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        let types = [];

        try {
            types = JSON.parse(builder.dataset.types || '[]');
        } catch (error) {
            types = [];
        }

        if (!Array.isArray(types) || types.length === 0) {
            return;
        }

        const defaultType = types[0].value;
        const oldType = (builder.dataset.oldType || '').trim() || defaultType;
        const labels = {};

        types.forEach((type) => {
            labels[type.value] = type.label;
        });

        const typeOptionsHtml = (selected) => types.map((type) =>
            '<option value="' + type.value + '"' + (String(selected) === String(type.value) ? ' selected' : '') + '>'
            + escapeHtml(type.label) + '</option>'
        ).join('');

        let sections = [];

        const optionsForWithValues = (index, values) => {
            const options = Array.isArray(values.options) ? values.options : [];
            let html = '<div class="grid grid-cols-2 gap-2 mt-2">';

            for (let i = 0; i < 4; i++) {
                const option = options[i] ?? '';
                html += '<input type="text" name="questions[' + index + '][options][]" value="' + escapeHtml(option) + '" placeholder="الخيار ' + (i + 1) + '" class="border border-gray-300 rounded px-2 py-1 text-sm">';
            }

            return html + '</div>';
        };

        const correctField = (index, type, values) => {
            const correct = values.correct_answer ?? '';
            const correctOptions = Array.isArray(values.correct_options) ? values.correct_options.map(String) : [];
            const options = Array.isArray(values.options) ? values.options : [];

            if (type === 'mcq') {
                let html = '<select name="questions[' + index + '][correct_answer]" class="w-full border border-gray-300 rounded px-2 py-1 text-sm mt-2"><option value="">الإجابة الصحيحة (رقم الخيار)</option>';

                for (let i = 0; i < 4; i++) {
                    const selected = String(correct) === String(i) || (correct !== '' && options[i] !== undefined && String(correct) === String(options[i]));
                    html += '<option value="' + i + '"' + (selected ? ' selected' : '') + '>الخيار ' + (i + 1) + '</option>';
                }

                return html + '</select>';
            }

            if (type === 'checkbox') {
                let html = '<div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-600">';

                for (let i = 0; i < 4; i++) {
                    const checked = correctOptions.includes(String(i)) || (options[i] !== undefined && correctOptions.includes(String(options[i])));
                    html += '<label class="flex items-center gap-1"><input type="checkbox" name="questions[' + index + '][correct_options][]" value="' + i + '"' + (checked ? ' checked' : '') + ' class="rounded border-gray-300 text-emerald-700"> الخيار ' + (i + 1) + '</label>';
                }

                return html + '</div>';
            }

            if (type === 'true_false') {
                return '<select name="questions[' + index + '][correct_answer]" class="w-full border border-gray-300 rounded px-2 py-1 text-sm mt-2">'
                    + '<option value="true"' + (String(correct) === 'true' ? ' selected' : '') + '>صح</option>'
                    + '<option value="false"' + (String(correct) === 'false' ? ' selected' : '') + '>خطأ</option></select>';
            }

            if (type === 'short') {
                return '<input type="text" name="questions[' + index + '][correct_answer]" value="' + escapeHtml(correct) + '" placeholder="الإجابة المتوقعة (اختياري — بدونها يُصحح يدوياً)" class="w-full border border-gray-300 rounded px-2 py-1 text-sm mt-2">';
            }

            return '<p class="text-xs text-gray-400 mt-2">يُصحح هذا السؤال يدوياً من صفحة النتائج.</p>';
        };

        const rowHtml = (index, type, values) => {
            const hasOptions = type === 'mcq' || type === 'checkbox';

            return '<div class="flex items-center justify-between mb-2"><span class="text-sm font-bold text-gray-600">سؤال ' + (index + 1) + '</span>'
                + '<input type="number" name="questions[' + index + '][marks]" value="' + escapeHtml(values.marks ?? '') + '" step="0.5" min="0" max="1000" class="w-24 border border-gray-300 rounded px-2 py-1 text-sm" placeholder="1"></div>'
                + '<input type="hidden" name="questions[' + index + '][type]" value="' + escapeHtml(type) + '">'
                + '<textarea name="questions[' + index + '][text]" rows="2" required placeholder="نص السؤال" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">' + escapeHtml(values.text ?? '') + '</textarea>'
                + (hasOptions ? optionsForWithValues(index, values) : '')
                + correctField(index, type, values);
        };

        const currentSum = () => Array.from(rowsContainer.querySelectorAll('input[name$="[marks]"]'))
            .reduce((total, input) => {
                const raw = input.value.trim();
                const value = raw === '' ? 1 : parseFloat(raw);

                return total + (Number.isNaN(value) ? 0 : value);
            }, 0);

        const updateSum = () => {
            const sum = Math.round(currentSum() * 100) / 100;

            if (sumEl) {
                sumEl.textContent = String(sum);
            }

            if (!targetEl) {
                return;
            }

            const totalInput = form ? form.querySelector('input[name="total_marks"]') : null;

            if (!totalInput || totalInput.value === '') {
                targetEl.textContent = '';
                return;
            }

            const total = parseFloat(totalInput.value) || 0;

            if (Math.abs(sum - total) < 0.01) {
                targetEl.textContent = '(مطابق للدرجة الكلية)';
                targetEl.className = 'text-xs text-emerald-600';
            } else {
                const diff = Math.round((total - sum) * 100) / 100;
                targetEl.textContent = '(الدرجة الكلية: ' + total + ' — المتبقي: ' + diff + ')';
                targetEl.className = 'text-xs text-amber-600';
            }
        };

        const sectionHtml = (section) => {
            return '<div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end border border-gray-200 rounded-lg p-3 bg-gray-50">'
                + '<div><label class="block text-sm font-medium text-gray-700 mb-1">نوع الأسئلة</label>'
                + '<select data-section-type class="w-full border border-gray-300 rounded-lg px-3 py-2">' + typeOptionsHtml(section.type) + '</select></div>'
                + '<div><label class="block text-sm font-medium text-gray-700 mb-1">عدد الأسئلة</label>'
                + '<input type="number" data-section-count value="' + escapeHtml(section.count ?? 1) + '" min="1" max="100" class="w-full border border-gray-300 rounded-lg px-3 py-2"></div>'
                + '<div><label class="block text-sm font-medium text-gray-700 mb-1">علامة السؤال <span class="text-gray-400 text-xs">اختياري — افتراضي 1</span></label>'
                + '<input type="number" data-section-marks value="' + escapeHtml(section.marks ?? '') + '" step="0.5" min="0" max="1000" placeholder="1" class="w-full border border-gray-300 rounded-lg px-3 py-2"></div>'
                + '<button type="button" data-section-remove class="text-red-600 hover:underline text-sm py-2">حذف القسم</button>'
                + '</div>';
        };

        const renderSections = () => {
            sectionsContainer.innerHTML = '';

            sections.forEach((section, index) => {
                const wrapper = document.createElement('div');
                wrapper.innerHTML = sectionHtml(section);

                wrapper.querySelector('[data-section-type]').addEventListener('change', (event) => {
                    sections[index].type = event.target.value;
                });
                wrapper.querySelector('[data-section-count]').addEventListener('input', (event) => {
                    sections[index].count = Math.min(100, Math.max(1, parseInt(event.target.value || '1', 10)));
                });
                wrapper.querySelector('[data-section-marks]').addEventListener('input', (event) => {
                    sections[index].marks = event.target.value;
                });
                wrapper.querySelector('[data-section-remove]').addEventListener('click', () => {
                    sections.splice(index, 1);
                    renderSections();
                });

                sectionsContainer.appendChild(wrapper);
            });
        };

        const buildSectionsFromRows = (rows) => rows.reduce((result, row) => {
            const type = row.type || oldType;
            const last = result[result.length - 1];

            if (last && String(last.type) === String(type)) {
                last.count += 1;
            } else {
                result.push({ type, count: 1, marks: row.marks ?? '' });
            }

            return result;
        }, []);

        const collectCurrentRows = () => Array.from(rowsContainer.querySelectorAll('[data-question-row]')).map((wrapper) => {
            const textarea = wrapper.querySelector('textarea[name$="[text]"]');
            const marks = wrapper.querySelector('input[name$="[marks]"]');
            const type = wrapper.querySelector('input[name$="[type]"]');
            const options = Array.from(wrapper.querySelectorAll('input[name$="[options][]"]')).map((input) => input.value);
            const correctAnswer = wrapper.querySelector('select[name$="[correct_answer]"]');
            const correctOptions = Array.from(wrapper.querySelectorAll('input[name$="[correct_options][]"]:checked')).map((input) => input.value);

            return {
                text: textarea ? textarea.value : '',
                marks: marks ? marks.value : '',
                options: options,
                correct_answer: correctAnswer ? correctAnswer.value : '',
                correct_options: correctOptions,
                type: type ? type.value : '',
            };
        });

        const render = (rows) => {
            rowsContainer.innerHTML = '';

            let currentType = null;

            rows.forEach((row, index) => {
                const type = row.type || oldType || defaultType;

                if (type !== currentType) {
                    currentType = type;
                    const count = rows.filter((candidate) => (candidate.type || oldType || defaultType) === type).length;
                    const heading = document.createElement('h3');
                    heading.className = 'text-sm font-bold text-emerald-700 bg-emerald-50 rounded-lg px-3 py-2 mt-4';
                    heading.textContent = (labels[type] || type) + ' (' + count + (count === 1 ? ' سؤال' : ' أسئلة') + ')';
                    rowsContainer.appendChild(heading);
                }

                const wrapper = document.createElement('div');
                wrapper.className = 'border border-gray-200 rounded-lg p-3';
                wrapper.dataset.questionRow = 'true';
                wrapper.innerHTML = rowHtml(index, type, row);
                rowsContainer.appendChild(wrapper);
            });

            updateSum();
        };

        generateButton.addEventListener('click', () => {
            const existing = collectCurrentRows();
            const rows = [];
            let position = 0;

            sections.forEach((section) => {
                const count = Math.min(100, Math.max(1, parseInt(section.count || '1', 10)));

                for (let i = 0; i < count; i++) {
                    const previous = existing[position] ?? null;
                    const type = section.type || defaultType;

                    if (previous && String(previous.type) === String(type)) {
                        rows.push(previous);
                    } else {
                        rows.push({
                            type: type,
                            marks: section.marks ?? '',
                            text: '',
                            options: [],
                            correct_answer: '',
                            correct_options: [],
                        });
                    }

                    position += 1;
                }
            });

            render(rows);
        });

        addSectionButton.addEventListener('click', () => {
            sections.push({ type: defaultType, count: 1, marks: '' });
            renderSections();
        });

        rowsContainer.addEventListener('input', updateSum);
        rowsContainer.addEventListener('change', updateSum);

        if (form) {
            const totalInput = form.querySelector('input[name="total_marks"]');
            if (totalInput) {
                totalInput.addEventListener('input', updateSum);
            }
        }

        let initial = [];

        try {
            initial = JSON.parse(builder.dataset.old || '[]');
        } catch (error) {
            initial = [];
        }

        initial = Array.isArray(initial) ? initial : [];

        if (initial.length > 0) {
            sections = buildSectionsFromRows(initial);
            renderSections();
            render(initial);
        } else {
            sections = [{ type: defaultType, count: 1, marks: '' }];
            renderSections();
            generateButton.click();
        }
    });
}

/* ============================================================
   التشغيل عند الجاهزية
============================================================ */
function initWorkSlotForms() {
    document.querySelectorAll('[data-work-slot-form]').forEach((form) => {
        const teacherInput = form.querySelector('[name="teacher_id"]');
        const dateInput = form.querySelector('[name="date"]');
        const startInput = form.querySelector('[name="start_time"]');
        const endInput = form.querySelector('[name="end_time"]');
        const durationEl = form.querySelector('[data-slot-duration]');
        const conflictEl = form.querySelector('[data-slot-conflict]');
        const url = form.dataset.daySlotsUrl;
        const slotId = form.dataset.slotId || null;

        if (!teacherInput || !dateInput || !startInput || !endInput) {
            return;
        }

        let existing = [];

        const toMinutes = (value) => {
            const parts = String(value || '').slice(0, 5).split(':').map((part) => parseInt(part, 10));
            return (parts[0] || 0) * 60 + (parts[1] || 0);
        };

        const formatDuration = (minutes) => {
            const hours = Math.floor(minutes / 60);
            const rest = minutes % 60;
            if (hours === 0 && rest === 0) return '0س';
            return (hours > 0 ? hours + 'س ' : '') + (rest > 0 ? rest + 'د' : '');
        };

        const updateDuration = () => {
            if (!durationEl) return;
            const start = toMinutes(startInput.value);
            const end = toMinutes(endInput.value);

            if (!startInput.value || !endInput.value) {
                durationEl.textContent = '—';
                return;
            }

            if (end < start) {
                durationEl.textContent = 'فترة تعبر منتصف الليل (غير مسموحة)';
                durationEl.classList.add('text-red-600');
                return;
            }

            durationEl.classList.remove('text-red-600');
            durationEl.textContent = 'المدة: ' + formatDuration(end - start);
        };

        const checkOverlap = () => {
            if (!conflictEl) return;
            const start = toMinutes(startInput.value);
            const end = toMinutes(endInput.value);

            if (!startInput.value || !endInput.value || end <= start) {
                conflictEl.classList.add('hidden');
                return;
            }

            const conflict = existing.find((slot) => slot.id !== slotId && start < toMinutes(slot.end) && end > toMinutes(slot.start));

            if (conflict) {
                conflictEl.textContent = 'تحذير: تتعارض مع ' + conflict.start + ' — ' + conflict.end;
                conflictEl.classList.remove('hidden');
            } else {
                conflictEl.classList.add('hidden');
            }
        };

        const refreshSlots = () => {
            if (!url || !teacherInput.value || !dateInput.value) {
                existing = [];
                return;
            }

            fetch(url + '?teacher_id=' + encodeURIComponent(teacherInput.value) + '&date=' + encodeURIComponent(dateInput.value), {
                headers: { Accept: 'application/json' },
            })
                .then((response) => (response.ok ? response.json() : { slots: [] }))
                .then((data) => {
                    existing = Array.isArray(data.slots) ? data.slots : [];
                    checkOverlap();
                })
                .catch(() => {
                    existing = [];
                });
        };

        [startInput, endInput].forEach((input) => {
            input.addEventListener('change', () => {
                updateDuration();
                checkOverlap();
            });
            input.addEventListener('input', updateDuration);
        });

        [teacherInput, dateInput].forEach((input) => input.addEventListener('change', refreshSlots));

        updateDuration();
        refreshSlots();
    });
}

function initQuickPay() {
    const roots = Array.from(document.querySelectorAll('[data-quick-pay]'));
    if (roots.length === 0) return;

    const closeAll = (except) => {
        roots.forEach((root) => {
            if (root === except) return;
            const panel = root.querySelector('[data-quick-pay-panel]');
            const toggle = root.querySelector('[data-quick-pay-toggle]');
            if (panel && !panel.hidden) {
                panel.hidden = true;
                toggle?.setAttribute('aria-expanded', 'false');
            }
        });
    };

    roots.forEach((root) => {
        const toggle = root.querySelector('[data-quick-pay-toggle]');
        const panel = root.querySelector('[data-quick-pay-panel]');
        if (!toggle || !panel || !panel.hidden) return;

        toggle.addEventListener('click', () => {
            closeAll(root);
            panel.hidden = !panel.hidden;
            toggle.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
            if (!panel.hidden) {
                panel.querySelector('[data-quick-pay-amount]')?.focus();
            }
        });

        root.querySelector('[data-quick-pay-close]')?.addEventListener('click', () => {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-quick-pay]')) {
            closeAll(null);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAll(null);
        }
    });
}

function initQuickSlotForms() {
    document.querySelectorAll('[data-quick-slot-form]').forEach((form) => {
        const teacherInput = form.querySelector('[name="teacher_id"]');
        const dateInput = form.querySelector('[name="date"]');
        const hoursInput = form.querySelector('[data-quick-slot-hours]');
        const startInput = form.querySelector('[data-quick-slot-start]');
        const previewEl = form.querySelector('[data-quick-slot-preview]');
        const conflictEl = form.querySelector('[data-quick-slot-conflict]');
        const url = form.dataset.daySlotsUrl;
        const defaultStart = '08:00';
        const chipClasses = ['bg-emerald-50', 'border-emerald-400', 'text-emerald-700'];

        if (!dateInput || !hoursInput) return;

        let existing = [];

        const toMinutes = (value) => {
            const parts = String(value || '').slice(0, 5).split(':').map((part) => parseInt(part, 10));
            return (parts[0] || 0) * 60 + (parts[1] || 0);
        };

        const formatDuration = (minutes) => {
            const hours = Math.floor(minutes / 60);
            const rest = minutes % 60;
            if (hours === 0 && rest === 0) return '0س';
            return (hours > 0 ? hours + 'س ' : '') + (rest > 0 ? rest + 'د' : '');
        };

        const formatTime = (minutes) => {
            const h = Math.floor(minutes / 60);
            const m = minutes % 60;
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        };

        const startValue = () => (startInput?.value ? startInput.value.slice(0, 5) : defaultStart);

        const checkOverlap = (start, end) => {
            if (!conflictEl) return;
            const conflict = existing.find((slot) => start < toMinutes(slot.end) && end > toMinutes(slot.start));

            if (conflict) {
                conflictEl.textContent = 'تحذير: تتعارض مع ' + conflict.start + ' — ' + conflict.end;
                conflictEl.classList.remove('hidden');
            } else {
                conflictEl.classList.add('hidden');
            }
        };

        const updatePreview = () => {
            const hours = parseFloat(hoursInput.value || '0');

            if (!previewEl) return;

            if (!hours || hours <= 0) {
                previewEl.textContent = 'اختر عدد الساعات';
                previewEl.classList.remove('text-red-600');
                conflictEl?.classList.add('hidden');
                return;
            }

            const start = toMinutes(startValue());
            const end = start + Math.round(hours * 60);

            if (end >= 24 * 60) {
                previewEl.textContent = 'الفترة تعبر منتصف الليل — غير مسموحة';
                previewEl.classList.add('text-red-600');
                conflictEl?.classList.add('hidden');
                return;
            }

            previewEl.classList.remove('text-red-600');
            previewEl.textContent = 'من ' + formatTime(start) + ' إلى ' + formatTime(end) + ' — ' + formatDuration(end - start);
            checkOverlap(start, end);
        };

        const refreshSlots = () => {
            if (!url || !teacherInput?.value || !dateInput.value) {
                existing = [];
                return;
            }

            fetch(url + '?teacher_id=' + encodeURIComponent(teacherInput.value) + '&date=' + encodeURIComponent(dateInput.value), {
                headers: { Accept: 'application/json' },
            })
                .then((response) => (response.ok ? response.json() : { slots: [] }))
                .then((data) => {
                    existing = Array.isArray(data.slots) ? data.slots : [];
                    updatePreview();
                })
                .catch(() => {
                    existing = [];
                });
        };

        form.querySelectorAll('[data-quick-slot-chip]').forEach((chip) => {
            chip.addEventListener('click', () => {
                hoursInput.value = chip.dataset.quickSlotChip;
                form.querySelectorAll('[data-quick-slot-chip]').forEach((other) => {
                    other.classList.remove(...chipClasses);
                    if (other === chip) {
                        other.classList.add(...chipClasses);
                    }
                });
                updatePreview();
            });
        });

        [hoursInput, startInput, dateInput].forEach((input) => {
            if (!input) return;
            input.addEventListener('input', updatePreview);
            input.addEventListener('change', updatePreview);
        });

        teacherInput?.addEventListener('change', refreshSlots);
        dateInput.addEventListener('change', refreshSlots);

        updatePreview();
        refreshSlots();
    });
}

function initCollapsibles() {
    document.querySelectorAll('[data-collapse-toggle]').forEach((toggle) => {
        const panel = toggle.nextElementSibling;
        if (!panel || !panel.hasAttribute('data-collapse-panel')) return;

        toggle.addEventListener('click', () => {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            panel.classList.toggle('hidden', expanded);
            toggle.querySelector('[data-collapse-chevron]')?.classList.toggle('rotate-180', !expanded);
        });
    });
}

function initAttendanceTrees() {
    document.querySelectorAll('[data-attendance-tree-toggle]').forEach((toggle) => {
        const panel = toggle.nextElementSibling;
        if (!panel || !panel.hasAttribute('data-attendance-tree-panel')) return;

        toggle.addEventListener('click', () => {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            panel.classList.toggle('hidden', expanded);
            toggle.querySelector('[data-attendance-tree-chevron]')?.classList.toggle('rotate-180', !expanded);
        });
    });
}

function initSessionGenderFilters() {
    document.querySelectorAll('[data-session-gender-target]').forEach((sessionSelect) => {
        const scope = sessionSelect.closest('form') || document;
        const genderSelect = scope.querySelector('[data-session-gender-source]');
        if (!genderSelect) return;

        const apply = () => {
            const gender = genderSelect.value;
            let selectedHidden = false;

            sessionSelect.querySelectorAll('option[data-gender]').forEach((option) => {
                const optionGender = option.dataset.gender || null;
                const matches = !gender || optionGender === null || optionGender === gender;
                option.hidden = !matches;
                option.disabled = !matches;

                if (option.selected && !matches) {
                    selectedHidden = true;
                }
            });

            if (selectedHidden) {
                sessionSelect.value = '';
            }
        };

        genderSelect.addEventListener('change', apply);
        apply();
    });
}

function initExamTargetPickers() {
    document.querySelectorAll('[data-exam-target-picker]').forEach((picker) => {
        const modeInputs = Array.from(picker.querySelectorAll('[data-exam-target-mode]'));
        const sessionSelect = picker.querySelector('[data-exam-target-session]');
        const classroomBlock = picker.querySelector('[data-exam-target-classrooms]');
        const classroomRows = Array.from(picker.querySelectorAll('[data-exam-target-classroom]'));
        const classroomInputs = Array.from(picker.querySelectorAll('[data-exam-target-classroom-input]'));
        const sectionBlock = picker.querySelector('[data-exam-target-section]');
        const sectionSelect = picker.querySelector('[data-exam-target-section-select]');
        if (modeInputs.length === 0 || !sessionSelect) return;

        const currentMode = () => modeInputs.find((input) => input.checked)?.value ?? 'classrooms';

        const apply = () => {
            const mode = currentMode();
            const shift = sessionSelect.value;
            const shiftWide = mode === 'shift';
            const selected = classroomInputs.filter((input) => input.checked);

            classroomRows.forEach((row) => {
                const rowSession = row.dataset.session || '';
                const matchesShift = !shift || rowSession === '' || rowSession === shift;
                const visible = !shiftWide && matchesShift;

                row.classList.toggle('hidden', !visible);

                const input = row.querySelector('[data-exam-target-classroom-input]');
                if (input) {
                    input.disabled = !visible;
                }
            });

            if (sectionSelect) {
                const activeClassroom = selected.length === 1 ? selected[0].value : null;

                Array.from(sectionSelect.options).forEach((option) => {
                    const classroomId = option.dataset.classroom || '';
                    const matches = classroomId === '' || classroomId === activeClassroom;
                    option.hidden = !matches;
                    option.disabled = !matches;
                });

                if (selected.length !== 1) {
                    sectionSelect.value = '';
                }
            }

            classroomBlock?.classList.toggle('opacity-50', shiftWide);
            sectionBlock?.classList.toggle('opacity-50', selected.length !== 1);
        };

        modeInputs.forEach((input) => input.addEventListener('change', apply));
        sessionSelect.addEventListener('change', apply);
        classroomInputs.forEach((input) => input.addEventListener('change', apply));
        apply();
    });
}

function initMemorizationEditors() {
    document.querySelectorAll('[data-memorization-editor]').forEach((editor) => {
        const toggle = editor.querySelector('[data-memorization-toggle]');
        const panel = editor.querySelector('[data-memorization-panel]');
        const close = editor.querySelector('[data-memorization-close]');

        if (!toggle || !panel) {
            return;
        }

        const setOpen = (open) => {
            panel.classList.toggle('hidden', !open);

            if (open) {
                panel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        };

        toggle.addEventListener('click', () => setOpen(panel.classList.contains('hidden')));
        close?.addEventListener('click', () => setOpen(false));
    });
}

/* ============================================================
   10) قوائم اختيار قابلة للبحث (الطلاب والمعلمون وأولياء الأمور)
=========================================================== */
const SEARCHABLE_SELECT_NAMES = new Set([
    'student_id',
    'teacher_id',
    'supervisor_id',
    'evaluated_by',
    'recipient_id',
    'assigned_to',
    'to_id',
    'from_id',
    'user_id',
]);

const SEARCHABLE_LAYOUT_CLASSES = /^(?:w-|min-w-|max-w-|flex-|grow|shrink|basis-|col-|row-|order-|self-|justify-self|place-self|align-self|sm:|md:|lg:|xl:|2xl:)/;

const searchableSelectClosers = [];

function normalizeSearchTerm(value) {
    return String(value ?? '')
        .toLowerCase()
        .replace(/[\u064B-\u0652\u0640]/g, '')
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/ى/g, 'ي')
        .replace(/ة/g, 'ه')
        .replace(/ؤ/g, 'و')
        .replace(/ئ/g, 'ي')
        .replace(/[^\p{L}\p{N}]+/gu, ' ')
        .trim();
}

function enhanceSearchableSelect(select) {
    if (select.dataset.searchableReady || select.multiple || select.options.length < 3) return;

    select.dataset.searchableReady = '1';

    const layoutClasses = select.className
        .split(/\s+/)
        .filter((token) => token !== '' && token !== 'hidden' && SEARCHABLE_LAYOUT_CLASSES.test(token));

    const wrapper = document.createElement('div');
    wrapper.className = ['relative', ...layoutClasses].join(' ');
    wrapper.dataset.searchableSelect = '';
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    select.setAttribute('tabindex', '-1');
    select.style.position = 'absolute';
    select.style.width = '1px';
    select.style.height = '1px';
    select.style.padding = '0';
    select.style.margin = '-1px';
    select.style.overflow = 'hidden';
    select.style.clip = 'rect(0, 0, 0, 0)';
    select.style.whiteSpace = 'nowrap';
    select.style.border = '0';

    const button = document.createElement('button');
    button.type = 'button';
    button.className = `${select.className} flex w-full items-center justify-between gap-2 bg-white text-right cursor-pointer disabled:cursor-not-allowed disabled:opacity-60`
        .replace(/\s+/g, ' ')
        .trim();
    button.dataset.searchableButton = '';
    button.setAttribute('aria-haspopup', 'listbox');
    button.setAttribute('aria-expanded', 'false');

    const buttonLabel = document.createElement('span');
    buttonLabel.className = 'truncate';
    button.appendChild(buttonLabel);

    const chevron = document.createElement('span');
    chevron.className = 'shrink-0 text-gray-400 transition-transform duration-200';
    chevron.setAttribute('aria-hidden', 'true');
    chevron.textContent = '▾';
    button.appendChild(chevron);

    wrapper.appendChild(button);

    const panel = document.createElement('div');
    panel.className = 'hidden fixed z-[80] rounded-xl border border-gray-200 bg-white shadow-2xl overflow-hidden';
    panel.dataset.searchablePanel = '';

    const hint = document.createElement('div');
    hint.className = 'hidden border-b border-red-100 bg-red-50 px-3 py-2 text-xs font-bold text-red-700';
    hint.textContent = 'الرجاء اختيار قيمة من القائمة';
    panel.appendChild(hint);

    const searchWrap = document.createElement('div');
    searchWrap.className = 'border-b border-gray-100 bg-gray-50/80 p-2';

    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.className = 'w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm focus:border-emerald-500 focus:outline-none';
    searchInput.placeholder = 'ابحث بالاسم...';
    searchInput.setAttribute('autocomplete', 'off');
    searchInput.setAttribute('aria-label', 'بحث في القائمة');
    searchInput.dataset.searchableInput = '';
    searchWrap.appendChild(searchInput);

    const list = document.createElement('div');
    list.className = 'max-h-64 overflow-y-auto py-1';
    list.setAttribute('role', 'listbox');
    list.dataset.searchableList = '';

    const empty = document.createElement('div');
    empty.className = 'hidden px-3 py-4 text-center text-sm text-gray-400';
    empty.textContent = 'لا توجد نتائج مطابقة';

    panel.appendChild(searchWrap);
    panel.appendChild(list);
    panel.appendChild(empty);
    document.body.appendChild(panel);

    let items = [];
    let visibleItems = [];
    let activeIndex = -1;
    let isOpen = false;

    const optionLabel = (option) => (option?.textContent ?? '').trim();

    const placeholderLabel = () => {
        const option = Array.from(select.options).find((item) => item.value === '');
        return optionLabel(option) || '— اختر —';
    };

    const markSelected = () => {
        items.forEach((item) => {
            const isSelected = item.dataset.value !== '' && item.dataset.value === select.value;
            item.classList.toggle('bg-emerald-50', isSelected);
            item.classList.toggle('font-bold', isSelected);
            item.classList.toggle('text-emerald-800', isSelected);
            item.setAttribute('aria-selected', isSelected ? 'true' : 'false');
        });
    };

    const highlightActive = () => {
        items.forEach((item) => item.classList.remove('bg-gray-100'));
        if (activeIndex < 0 || activeIndex >= visibleItems.length) return;

        const active = visibleItems[activeIndex];
        active.classList.add('bg-gray-100');
        active.scrollIntoView({ block: 'nearest' });
    };

    const applyFilter = () => {
        const term = normalizeSearchTerm(searchInput.value);
        let count = 0;

        items.forEach((item) => {
            const matches = term === '' || item.dataset.search.includes(term);
            item.classList.toggle('hidden', !matches);
            if (matches) count += 1;
        });

        empty.classList.toggle('hidden', count > 0);
        visibleItems = items.filter((item) => !item.classList.contains('hidden') && !item.disabled);
        activeIndex = visibleItems.findIndex((item) => item.dataset.value === select.value);
        highlightActive();
    };

    const buildItems = () => {
        list.innerHTML = '';
        items = [];

        Array.from(select.options).forEach((option) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.setAttribute('role', 'option');
            item.dataset.searchableOption = '';
            item.dataset.value = option.value;
            item.dataset.search = normalizeSearchTerm(optionLabel(option));
            item.className = 'flex w-full items-center gap-2 px-3 py-2 text-right text-sm transition hover:bg-emerald-50';

            if (option.disabled) {
                item.disabled = true;
                item.classList.add('cursor-not-allowed', 'opacity-40');
            }

            const text = document.createElement('span');
            text.className = 'truncate';
            text.textContent = optionLabel(option) || '—';
            item.appendChild(text);

            item.addEventListener('click', () => choose(option.value));
            list.appendChild(item);
            items.push(item);
        });

        markSelected();
    };

    const positionPanel = () => {
        const rect = button.getBoundingClientRect();
        panel.style.top = `${Math.round(rect.bottom + 4)}px`;
        panel.style.left = `${Math.round(rect.left)}px`;
        panel.style.width = `${Math.round(rect.width)}px`;
    };

    const closePanel = () => {
        if (!isOpen) return;
        isOpen = false;
        panel.classList.add('hidden');
        button.setAttribute('aria-expanded', 'false');
        chevron.classList.remove('rotate-180');
        window.removeEventListener('scroll', positionPanel, true);
        window.removeEventListener('resize', positionPanel);
    };

    const openPanel = (showHint = false) => {
        if (isOpen || select.disabled || button.offsetParent === null) return;

        searchableSelectClosers.forEach((close) => close());
        isOpen = true;
        panel.classList.remove('hidden');
        hint.classList.toggle('hidden', !showHint);
        button.setAttribute('aria-expanded', 'true');
        chevron.classList.add('rotate-180');
        searchInput.value = '';
        applyFilter();
        positionPanel();
        window.addEventListener('scroll', positionPanel, true);
        window.addEventListener('resize', positionPanel);
        window.requestAnimationFrame(() => searchInput.focus());
    };

    const choose = (value) => {
        select.value = value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        syncLabel();
        markSelected();
        closePanel();
        button.focus();
    };

    const syncLabel = () => {
        const option = select.selectedOptions[0];
        const hasValue = !!option && option.value !== '';
        buttonLabel.textContent = hasValue ? optionLabel(option) : placeholderLabel();
        buttonLabel.classList.toggle('text-gray-400', !hasValue);
        button.disabled = select.disabled;
        button.classList.toggle('hidden', select.hidden || select.classList.contains('hidden'));
        button.setAttribute('aria-required', select.required ? 'true' : 'false');

        if (button.classList.contains('hidden') || select.disabled) closePanel();
    };

    searchableSelectClosers.push(closePanel);

    button.addEventListener('click', () => (isOpen ? closePanel() : openPanel()));

    button.addEventListener('keydown', (event) => {
        if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
            event.preventDefault();
            openPanel();
        }
    });

    searchInput.addEventListener('input', applyFilter);

    searchInput.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closePanel();
            button.focus();
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (visibleItems.length === 0) return;

            activeIndex = event.key === 'ArrowDown'
                ? (activeIndex + 1) % visibleItems.length
                : (activeIndex - 1 + visibleItems.length) % visibleItems.length;
            highlightActive();
            return;
        }

        if (event.key === 'Enter') {
            event.preventDefault();
            const target = visibleItems[activeIndex] || visibleItems[0];
            if (target) choose(target.dataset.value);
        }
    });

    select.addEventListener('change', () => {
        syncLabel();
        markSelected();
    });

    select.addEventListener('invalid', (event) => {
        event.preventDefault();
        openPanel(true);
    });

    select.closest('form')?.addEventListener('reset', () => {
        window.setTimeout(() => {
            syncLabel();
            markSelected();
        }, 0);
    });

    new MutationObserver(() => {
        syncLabel();
        buildItems();
        if (isOpen) applyFilter();
    }).observe(select, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'disabled', 'hidden'],
    });

    buildItems();
    syncLabel();
}

function initSearchableSelects() {
    document.querySelectorAll('select[name]').forEach((select) => {
        if (select.dataset.searchableReady) return;
        if (select.dataset.searchable === 'off') return;

        const name = select.getAttribute('name') || '';
        const isPersonSelect = SEARCHABLE_SELECT_NAMES.has(name) || select.hasAttribute('data-searchable');

        if (isPersonSelect) enhanceSearchableSelect(select);
    });

    if (!initSearchableSelects.bound) {
        initSearchableSelects.bound = true;

        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target : null;
            if (target?.closest('[data-searchable-select]') || target?.closest('[data-searchable-panel]')) return;
            searchableSelectClosers.forEach((close) => close());
        });
    }
}

function initPermissionMatrices() {
    document.querySelectorAll('[data-permission-matrix]').forEach((root) => {
        const isUserMode = root.dataset.mode === 'user';
        const search = root.querySelector('[data-permission-search]');
        const counter = root.querySelector('[data-permission-counter]');
        const rows = Array.from(root.querySelectorAll('[data-permission-row]'));
        const groups = Array.from(root.querySelectorAll('[data-permission-group]'));
        const selects = Array.from(root.querySelectorAll('[data-permission-select]'));

        const baseSelectClass = 'border border-gray-300 rounded-lg px-2 py-1.5 text-sm w-full md:w-56';

        const paintSelect = (select) => {
            select.className = baseSelectClass;
            const value = select.value;

            if (isUserMode) {
                if (value === 'deny') select.classList.add('bg-red-50', 'border-red-300', 'text-red-700');
                else if (value !== 'inherit') select.classList.add('bg-amber-50', 'border-amber-300', 'text-amber-800');
            } else if (value !== '') {
                select.classList.add('bg-emerald-50', 'border-emerald-300', 'text-emerald-800');
            }
        };

        const updateCounters = () => {
            const changed = selects.filter((select) => (isUserMode ? select.value !== 'inherit' : select.value !== '')).length;
            if (counter) counter.textContent = isUserMode ? changed + ' تجاوز مباشر' : changed + ' صلاحية ممنوحة';
        };

        const updateBadges = () => {
            rows.forEach((row) => {
                const select = row.querySelector('[data-permission-select]');
                const wrap = row.querySelector('[data-permission-label-wrap]');
                let badge = row.querySelector('[data-override-badge]');

                if (isUserMode && select && select.value !== 'inherit') {
                    if (!badge && wrap) {
                        badge = document.createElement('span');
                        badge.dataset.overrideBadge = '1';
                        wrap.appendChild(badge);
                    }
                    if (badge) {
                        badge.className = 'px-1.5 py-0.5 rounded-full text-[10px] font-bold ' + (select.value === 'deny' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700');
                        badge.textContent = 'تجاوز';
                    }
                } else if (badge) {
                    badge.remove();
                }
            });
        };

        const syncGroupVisibility = () => {
            groups.forEach((group) => {
                const hasVisible = Array.from(group.querySelectorAll('[data-permission-row]')).some((row) => !row.hidden);
                group.classList.toggle('hidden', !hasVisible);
            });
        };

        const setValues = (valuesByCode, defaultValue) => {
            selects.forEach((select) => {
                const code = select.closest('[data-permission-row]')?.dataset.code;
                const value = Object.prototype.hasOwnProperty.call(valuesByCode, code) ? valuesByCode[code] : defaultValue;
                const option = Array.from(select.options).find((opt) => opt.value === value);
                if (!option || option.disabled) return;
                select.value = value;
                paintSelect(select);
            });
            updateCounters();
            updateBadges();
        };

        search?.addEventListener('input', () => {
            const query = (search.value || '').trim().toLowerCase();
            rows.forEach((row) => {
                const haystack = (row.dataset.code + ' ' + row.dataset.label).toLowerCase();
                row.hidden = query !== '' && !haystack.includes(query);
            });
            syncGroupVisibility();
        });

        root.querySelectorAll('[data-permission-global-action]').forEach((button) => {
            button.addEventListener('click', () => {
                const action = button.dataset.permissionGlobalAction;
                setValues({}, action === 'inherit' ? 'inherit' : (action === 'revoke' ? '' : 'mosque'));
            });
        });

        groups.forEach((group) => {
            const toggle = group.querySelector('[data-permission-group-toggle]');
            const panel = group.querySelector('[data-permission-group-panel]');
            const chevron = group.querySelector('[data-permission-group-chevron]');

            toggle?.addEventListener('click', () => {
                const collapsed = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                panel?.classList.toggle('hidden', collapsed);
                chevron?.classList.toggle('rotate-180', !collapsed);
            });

            group.querySelectorAll('[data-permission-group-action]').forEach((button) => {
                button.addEventListener('click', () => {
                    const action = button.dataset.permissionGroupAction;
                    const target = action === 'inherit' ? 'inherit' : (action === 'revoke' ? '' : action);
                    group.querySelectorAll('[data-permission-select]').forEach((select) => {
                        const option = Array.from(select.options).find((opt) => opt.value === target);
                        if (!option || option.disabled) return;
                        select.value = target;
                        paintSelect(select);
                    });
                    updateCounters();
                    updateBadges();
                });
            });
        });

        selects.forEach((select) => select.addEventListener('change', () => {
            paintSelect(select);
            updateCounters();
            updateBadges();
        }));

        const collapseAll = (collapsed) => {
            groups.forEach((group) => {
                const toggle = group.querySelector('[data-permission-group-toggle]');
                const panel = group.querySelector('[data-permission-group-panel]');
                const chevron = group.querySelector('[data-permission-group-chevron]');
                if (!toggle || !panel) return;
                toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                panel.classList.toggle('hidden', collapsed);
                chevron?.classList.toggle('rotate-180', !collapsed);
            });
        };

        root.querySelector('[data-permission-collapse-all]')?.addEventListener('click', () => collapseAll(true));
        root.querySelector('[data-permission-expand-all]')?.addEventListener('click', () => collapseAll(false));

        const copySelect = root.querySelector('[data-permission-copy]');
        copySelect?.addEventListener('change', () => {
            const option = copySelect.selectedOptions[0];
            if (!option?.value) return;
            let grants = {};
            try { grants = JSON.parse(option.dataset.grants || '{}'); } catch (e) { grants = {}; }
            setValues(grants, '');
            copySelect.value = '';
        });

        updateCounters();
        updateBadges();
        selects.forEach(paintSelect);
    });
}

function initApp() {
    initSidebarCollapse();
    initSidebarGroups();
    revealOnScroll();
    initCounters();
    initFlashToasts();
    initPasswordToggles();
    initPhotoPreviews();
    initPortalAccountToggles();
    initVoiceRecorders();
    initSearchPickers();
    initWorkSlotForms();
    initQuickPay();
    initQuickSlotForms();
    initExamTimers();
    initExamQuestionBuilders();
    initAttendanceTrees();
    initCollapsibles();
    initSessionGenderFilters();
    initExamTargetPickers();
    initMemorizationEditors();
    initPermissionMatrices();
    initSearchableSelects();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}
