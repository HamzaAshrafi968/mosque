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
   6) زر "إغلاق" عام (أي زر يحمل class btn-dismiss-js يخفي أقرب عنصر)
============================================================ */
document.querySelectorAll('[data-dismiss-parent]').forEach((btn) => {
    btn.addEventListener('click', () => btn.closest(btn.dataset.dismissParent)?.remove());
});

/* ============================================================
   التشغيل عند الجاهزية
============================================================ */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        revealOnScroll();
        initCounters();
        initFlashToasts();
        initPasswordToggles();
    });
} else {
    revealOnScroll();
    initCounters();
    initFlashToasts();
    initPasswordToggles();
}
