import './bootstrap';

const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebar-overlay');
const toggleBtn = document.getElementById('sidebar-toggle');
const closeBtn = document.getElementById('sidebar-close');

function openSidebar() {
    sidebar?.classList.remove('translate-x-full');
    sidebar?.classList.add('translate-x-0');
    overlay?.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeSidebar() {
    sidebar?.classList.add('translate-x-full');
    sidebar?.classList.remove('translate-x-0');
    overlay?.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

toggleBtn?.addEventListener('click', openSidebar);
closeBtn?.addEventListener('click', closeSidebar);
overlay?.addEventListener('click', closeSidebar);

window.addEventListener('resize', () => {
    if (window.innerWidth >= 1024) {
        sidebar?.classList.remove('translate-x-full');
        overlay?.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
});
