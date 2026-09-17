import './bootstrap';

/* ============================================================
   1) القائمة الجانبية
============================================================ */
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebar-overlay');
const toggleBtn = document.getElementById('sidebar-toggle');
const closeBtn = document.getElementById('sidebar-close');

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
    }
});

sidebar?.addEventListener('click', (event) => {
    if (isDesktop()) return;
    const link = event.target.closest('a[href]');
    if (link && !link.getAttribute('href')?.startsWith('#')) {
        closeSidebar();
    }
});

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
   التشغيل عند الجاهزية
============================================================ */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        revealOnScroll();
        initCounters();
        initFlashToasts();
        initPasswordToggles();
        initPhotoPreviews();
        initVoiceRecorders();
    });
} else {
    revealOnScroll();
    initCounters();
    initFlashToasts();
    initPasswordToggles();
    initPhotoPreviews();
    initVoiceRecorders();
}
