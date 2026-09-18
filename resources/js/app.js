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
   التشغيل عند الجاهزية
=========================================================== */
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

function initApp() {
    initSidebarCollapse();
    initSidebarGroups();
    revealOnScroll();
    initCounters();
    initFlashToasts();
    initPasswordToggles();
    initPhotoPreviews();
    initVoiceRecorders();
    initSearchPickers();
    initWorkSlotForms();
    initExamTimers();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}
