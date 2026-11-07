import Swal from 'sweetalert2';

/*
 * Heartwise front-end extras: SweetAlert pop-ups, scroll reveal,
 * the swipe deck, and the "It's a match" celebration.
 */

/* ---------------------------------------------------------------
 * Device timezone → "tz" cookie. A VPN changes the IP address but not
 * the device clock, so the server compares the two (see ConnectionInspector).
 * ------------------------------------------------------------- */
try {
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (tz) document.cookie = `tz=${encodeURIComponent(tz)};path=/;max-age=31536000;samesite=lax`;
} catch (e) {
    // Older browsers: skip.
}

/* ---------------------------------------------------------------
 * Install as an app (PWA). Android/desktop Chrome offer an install
 * prompt; we show our own "Install the app" button when they do.
 * iPhone users add it with Share → "Add to Home Screen".
 * ------------------------------------------------------------- */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}

let installPrompt = null;

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    installPrompt = event;
    document.querySelectorAll('[data-install-app]').forEach((el) => (el.hidden = false));
});

document.addEventListener('click', async (event) => {
    if (!event.target.closest('[data-install-app]') || !installPrompt) return;
    installPrompt.prompt();
    await installPrompt.userChoice;
    installPrompt = null;
    document.querySelectorAll('[data-install-app]').forEach((el) => (el.hidden = true));
});

/* ---------------------------------------------------------------
 * SweetAlert2: every pop-up on the site (confirms, toasts, matches,
 * live alerts) uses the same look.
 * ------------------------------------------------------------- */
const brand = '#e11d5a';
const dark = () => document.documentElement.classList.contains('dark');

const swal = (options) =>
    Swal.fire({
        confirmButtonColor: brand,
        cancelButtonColor: '#71717a',
        background: dark() ? '#18181b' : '#fff',
        color: dark() ? '#fafafa' : '#18181b',
        ...options,
    });

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

window.Swal = Swal;
window.hwToast = (text, icon = 'success', options = {}) =>
    Toast.fire({ icon, title: text, background: dark() ? '#27272a' : '#fff', color: dark() ? '#fafafa' : '#18181b', ...options });

/* Server toasts (Flux::toast in PHP) are shown as SweetAlert toasts. */
window.addEventListener('toast-show', (event) => {
    const d = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    const variant = d?.dataset?.variant;
    const icon = { success: 'success', warning: 'warning', danger: 'error' }[variant] ?? 'info';
    const text = d?.slots?.text ?? '';
    const heading = d?.slots?.heading;
    window.hwToast(heading ? `${heading}: ${text}` : text, icon, { timer: d?.duration > 0 ? d.duration : 4000 });
});

/* ---------------------------------------------------------------
 * Confirm dialog: add data-confirm="Question?" to any button that has
 * wire:click (data-confirm-danger makes the button red).
 * ------------------------------------------------------------- */
let confirmed = null;

document.addEventListener(
    'click',
    (event) => {
        const el = event.target.closest('[data-confirm]');
        if (!el || el === confirmed) {
            confirmed = null;
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const danger = el.hasAttribute('data-confirm-danger');
        swal({
            title: el.dataset.confirm,
            icon: danger ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, continue',
            cancelButtonText: 'Cancel',
            confirmButtonColor: danger ? '#dc2626' : brand,
            reverseButtons: true,
            focusCancel: danger,
        }).then((result) => {
            if (!result.isConfirmed) return;
            confirmed = el;
            el.click();
        });
    },
    true,
);

/* ---------------------------------------------------------------
 * Reveal-on-scroll: elements with data-reveal fade/slide in.
 * ------------------------------------------------------------- */
document.documentElement.classList.add('reveal-ready');

function initReveal() {
    // Safety net: anything still hidden after 3 seconds is shown anyway.
    clearTimeout(window.__revealFallback);
    window.__revealFallback = setTimeout(() => {
        document.querySelectorAll('[data-reveal]:not(.is-revealed)').forEach((el) => {
            const r = el.getBoundingClientRect();
            if (r.top < window.innerHeight) el.classList.add('is-revealed');
        });
    }, 3000);

    const items = document.querySelectorAll('[data-reveal]:not(.is-revealed)');
    if (!('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-revealed'));
        return;
    }
    const io = new IntersectionObserver(
        (entries) =>
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-revealed');
                    io.unobserve(entry.target);
                }
            }),
        { threshold: 0.12 },
    );
    items.forEach((el) => io.observe(el));
}

document.addEventListener('DOMContentLoaded', () => {
    initReveal();

    // Livewire swaps content in without a page load; reveal anything new it adds.
    let queued = false;
    new MutationObserver(() => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            initReveal();
        });
    }).observe(document.body, { childList: true, subtree: true });
});
document.addEventListener('livewire:navigated', initReveal);

/* ---------------------------------------------------------------
 * "It's a match" celebration: dispatch('matched', { name, url })
 * ------------------------------------------------------------- */
window.addEventListener('matched', (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    const hearts = Array.from({ length: 18 }, () =>
        `<span style="left:${Math.random() * 100}%;animation-delay:${Math.random() * 0.6}s;font-size:${12 + Math.random() * 18}px">❤</span>`,
    ).join('');

    swal({
        html: `<div class="hw-match__hearts" aria-hidden="true">${hearts}</div>
            <p class="hw-match__eyebrow">It's a match!</p>
            <h2 class="hw-match__title"></h2>
            <p class="hw-match__text">You both liked each other. Say hello — and remember, never send money to someone you met online.</p>`,
        showCancelButton: true,
        confirmButtonText: 'Send a message',
        cancelButtonText: 'Keep going',
        reverseButtons: true,
        customClass: { popup: 'hw-swal-match' },
        didOpen: (popup) => {
            popup.querySelector('.hw-match__title').textContent = `You and ${detail?.name ?? 'your match'}`;
        },
    }).then((result) => {
        if (result.isConfirmed) window.Livewire?.navigate ? Livewire.navigate(detail?.url ?? '/messages') : (location.href = detail?.url ?? '/messages');
    });
});

/* ---------------------------------------------------------------
 * Live updates (from the live-pulse component every few seconds):
 * badges change and new messages, likes and matches pop up — no refresh.
 * ------------------------------------------------------------- */
const go = (url) => (window.Livewire?.navigate ? Livewire.navigate(url) : (location.href = url));

window.addEventListener('live-counts', (event) => {
    const counts = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    document.querySelectorAll('[data-live-badge]').forEach((badge) => {
        const value = Number(counts?.[badge.dataset.liveBadge] ?? 0);
        badge.textContent = value > 99 ? '99+' : String(value);
        badge.classList.toggle('hidden', value === 0);
    });
});

window.addEventListener('live-alert', (event) => {
    const alert = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    if (!alert) return;

    // Already looking at that chat? The message shows there by itself.
    let path = '';
    try {
        path = new URL(alert.url, location.origin).pathname;
    } catch (e) {}
    if (alert.kind === 'message' && path === location.pathname) return;

    if (alert.kind === 'match') {
        swal({
            title: alert.title,
            text: alert.body,
            icon: 'success',
            showCancelButton: true,
            confirmButtonText: 'Say hello',
            cancelButtonText: 'Later',
            reverseButtons: true,
            customClass: { popup: 'hw-swal-match' },
        }).then((result) => result.isConfirmed && go(alert.url));
        return;
    }

    window
        .hwToast(alert.title, alert.kind === 'message' ? 'info' : 'success', {
            text: alert.body,
            timer: 7000,
            didOpen: (toast) => {
                toast.style.cursor = 'pointer';
                toast.addEventListener('click', () => {
                    Swal.close();
                    go(alert.url);
                });
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            },
        });
});

/* ---------------------------------------------------------------
 * Swipe deck (Alpine): drag a card left to pass, right to like.
 * ------------------------------------------------------------- */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('swipeCard', (userId) => ({
        x: 0,
        y: 0,
        startX: 0,
        startY: 0,
        dragging: false,
        leaving: null,

        get style() {
            const rotate = this.x / 18;
            const transition = this.dragging ? 'none' : 'transform .35s cubic-bezier(.2,.8,.2,1), opacity .35s';
            return `transform: translate(${this.x}px, ${this.y}px) rotate(${rotate}deg); transition: ${transition}`;
        },
        get likeOpacity() {
            return Math.max(0, Math.min(1, this.x / 120));
        },
        get passOpacity() {
            return Math.max(0, Math.min(1, -this.x / 120));
        },

        down(e) {
            if (e.target.closest('button, a')) return;
            this.dragging = true;
            this.startX = e.clientX - this.x;
            this.startY = e.clientY - this.y;
            e.currentTarget.setPointerCapture?.(e.pointerId);
        },
        move(e) {
            if (!this.dragging) return;
            this.x = e.clientX - this.startX;
            this.y = (e.clientY - this.startY) * 0.3;
        },
        up() {
            if (!this.dragging) return;
            this.dragging = false;
            if (this.x > 110) return this.decide(true);
            if (this.x < -110) return this.decide(false);
            this.x = 0;
            this.y = 0;
        },
        decide(liked) {
            this.leaving = liked ? 'right' : 'left';
            this.x = liked ? window.innerWidth : -window.innerWidth;
            this.y = 40;
            setTimeout(() => this.$wire.react(userId, liked), 220);
        },
    }));
});
