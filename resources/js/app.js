import './bootstrap';
import { gsap } from 'gsap';
import { initFormCascade, initDashboard, initProfile } from './mblrc';
import { initIb39Dashboard } from './ib39';
import { initIb39FullMap } from './ib39-map';
import { initIb39Areas } from './ib39-areas';
import { initIb39BoundaryEditor } from './ib39-boundary-editor';
import { initRcspComments } from './rcsp-comments';
import { initConfirmDialogs } from './confirm-dialog';
import { initKatuparanDashboard } from './katuparan-dashboard';

window.gsap = gsap;

/**
 * SHIELD — vanilla JS interaction layer (no Alpine).
 * Behaviors are wired via data-attributes so Blade stays declarative.
 */
/**
 * Each feature self-gates on its own DOM markers, but they are also isolated:
 * one throwing must not stop the rest. Previously a single error silently took
 * out every module registered after it.
 */
function run(name, fn) {
    try {
        fn();
    } catch (error) {
        console.error(`[shield] ${name} failed to initialise:`, error);
    }
}

function boot() {
    run('sidebar', initSidebar);
    run('dropdowns', initDropdowns);
    run('modals', initModals);
    run('tabs', initTabs);
    run('flashToasts', initFlashToasts);
    run('reveal', revealOnLoad);

    run('mblrc.formCascade', initFormCascade);
    run('mblrc.dashboard', initDashboard);
    run('mblrc.profile', initProfile);
    run('ib39.dashboard', initIb39Dashboard);
    run('ib39.map', initIb39FullMap);
    run('ib39.areas', initIb39Areas);
    run('ib39.boundaryEditor', initIb39BoundaryEditor);
    run('rcsp.comments', initRcspComments);
    run('confirmDialogs', initConfirmDialogs);
    run('katuparan.dashboard', initKatuparanDashboard);
}

/*
 * `@vite` emits this as <script type="module">, which is deferred. Depending on
 * when the module graph finishes evaluating, DOMContentLoaded may already have
 * fired by the time this runs — in which case a listener for it never fires and
 * nothing initialises. Check readyState instead of assuming.
 */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}

// Mobile sidebar toggle -------------------------------------------------
function initSidebar() {
    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const open = () => {
        sidebar?.classList.remove('-translate-x-full');
        backdrop?.classList.remove('hidden');
    };
    const close = () => {
        sidebar?.classList.add('-translate-x-full');
        backdrop?.classList.add('hidden');
    };
    document.querySelectorAll('[data-sidebar-open]').forEach((b) => b.addEventListener('click', open));
    document.querySelectorAll('[data-sidebar-close]').forEach((b) => b.addEventListener('click', close));
    backdrop?.addEventListener('click', close);
}

// Click-to-toggle dropdowns (profile menu, etc.) ------------------------
function initDropdowns() {
    document.querySelectorAll('[data-dropdown]').forEach((root) => {
        const trigger = root.querySelector('[data-dropdown-trigger]');
        const menu = root.querySelector('[data-dropdown-menu]');
        if (!trigger || !menu) return;
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            menu.classList.toggle('hidden');
        });
    });
    document.addEventListener('click', () => {
        document.querySelectorAll('[data-dropdown-menu]').forEach((m) => m.classList.add('hidden'));
    });
}

// Lightweight modals ----------------------------------------------------
// Trigger:  [data-modal-open="id"]   Panel: #id with [data-modal]
// Close:    [data-modal-close] or backdrop / Escape.
function initModals() {
    const open = (id) => {
        const m = document.getElementById(id);
        if (!m) return;
        m.classList.remove('hidden');
        m.classList.add('flex');
        gsap.fromTo(m.querySelector('[data-modal-panel]'),
            { y: 20, opacity: 0 }, { y: 0, opacity: 1, duration: 0.25, ease: 'power2.out' });
    };
    const close = (m) => { m.classList.add('hidden'); m.classList.remove('flex'); };

    document.querySelectorAll('[data-modal-open]').forEach((b) =>
        b.addEventListener('click', () => open(b.dataset.modalOpen)));
    document.querySelectorAll('[data-modal]').forEach((m) => {
        m.addEventListener('click', (e) => {
            if (e.target === m || e.target.closest('[data-modal-close]')) close(m);
        });
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') document.querySelectorAll('[data-modal]:not(.hidden)').forEach(close);
    });
    window.openModal = open;
    window.closeModals = () => document.querySelectorAll('[data-modal]').forEach(close);
}

// Tabs ------------------------------------------------------------------
// Container [data-tabs]; buttons [data-tab-btn="key"]; panels [data-tab-panel="key"].
function initTabs() {
    document.querySelectorAll('[data-tabs]').forEach((root) => {
        const btns = root.querySelectorAll('[data-tab-btn]');
        const panels = root.querySelectorAll('[data-tab-panel]');
        const activate = (key) => {
            btns.forEach((b) => b.classList.toggle('tab-active', b.dataset.tabBtn === key));
            panels.forEach((p) => p.classList.toggle('hidden', p.dataset.tabPanel !== key));
        };
        btns.forEach((b) => b.addEventListener('click', () => activate(b.dataset.tabBtn)));
        if (btns.length) activate(btns[0].dataset.tabBtn);
    });
}

// Session-flash toasts --------------------------------------------------
function initFlashToasts() {
    document.querySelectorAll('[data-toast]').forEach((el) => {
        gsap.fromTo(el, { x: 40, opacity: 0 }, { x: 0, opacity: 1, duration: 0.4, ease: 'power2.out' });
        setTimeout(() => {
            gsap.to(el, { x: 40, opacity: 0, duration: 0.3, onComplete: () => el.remove() });
        }, 4500);
    });
}

// Subtle content reveal -------------------------------------------------
function revealOnLoad() {
    const items = document.querySelectorAll('[data-reveal]');
    if (!items.length) return;
    gsap.fromTo(
        items,
        { y: 16, opacity: 0 },
        { y: 0, opacity: 1, duration: 0.5, ease: 'power2.out', stagger: 0.06 },
    );
}
