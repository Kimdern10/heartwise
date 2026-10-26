/*
 * Heartwise front-end extras: in-page confirm dialogs, scroll reveal,
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
 * Confirm dialog
 * Add data-confirm="Question?" to any button that has wire:click.
 * Works everywhere (the browser's own confirm() popup is blocked in
 * some browsers and in-app web views, so we never rely on it).
 * ------------------------------------------------------------- */
let confirmed = null;

function openConfirm(message, danger, onYes) {
    const wrap = document.createElement('div');
    wrap.className = 'hw-confirm';
    wrap.innerHTML = `
        <div class="hw-confirm__backdrop"></div>
        <div class="hw-confirm__panel" role="alertdialog" aria-modal="true">
            <p class="hw-confirm__text"></p>
            <div class="hw-confirm__actions">
                <button type="button" class="hw-btn hw-btn--ghost" data-no>Cancel</button>
                <button type="button" class="hw-btn ${danger ? 'hw-btn--danger' : 'hw-btn--primary'}" data-yes>Yes, continue</button>
            </div>
        </div>`;
    wrap.querySelector('.hw-confirm__text').textContent = message;

    const close = () => {
        wrap.classList.add('is-leaving');
        setTimeout(() => wrap.remove(), 180);
        document.removeEventListener('keydown', onKey);
    };
    const onKey = (e) => e.key === 'Escape' && close();

    wrap.querySelector('[data-no]').addEventListener('click', close);
    wrap.querySelector('.hw-confirm__backdrop').addEventListener('click', close);
    wrap.querySelector('[data-yes]').addEventListener('click', () => {
        close();
        onYes();
    });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(wrap);
    wrap.querySelector('[data-yes]').focus();
}

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

        openConfirm(el.dataset.confirm, el.hasAttribute('data-confirm-danger'), () => {
            confirmed = el;
            el.click();
        });
    },
    true,
);

/* ---------------------------------------------------------------
 * Reveal-on-scroll: elements with data-reveal fade/slide in.
 * ------------------------------------------------------------- */
function initReveal() {
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
    const wrap = document.createElement('div');
    wrap.className = 'hw-match';
    wrap.innerHTML = `
        <div class="hw-match__backdrop"></div>
        <div class="hw-match__panel">
            <div class="hw-match__hearts" aria-hidden="true"></div>
            <p class="hw-match__eyebrow">It's a match!</p>
            <h2 class="hw-match__title"></h2>
            <p class="hw-match__text">You both liked each other. Say hello — and remember, never send money to someone you met online.</p>
            <div class="hw-match__actions">
                <button type="button" class="hw-btn hw-btn--ghost" data-close>Keep swiping</button>
                <a class="hw-btn hw-btn--primary" data-chat>Send a message</a>
            </div>
        </div>`;
    wrap.querySelector('.hw-match__title').textContent = `You and ${detail?.name ?? 'your match'}`;
    wrap.querySelector('[data-chat]').href = detail?.url ?? '/messages';

    const hearts = wrap.querySelector('.hw-match__hearts');
    for (let i = 0; i < 18; i++) {
        const h = document.createElement('span');
        h.textContent = '❤';
        h.style.left = `${Math.random() * 100}%`;
        h.style.animationDelay = `${Math.random() * 0.6}s`;
        h.style.fontSize = `${12 + Math.random() * 18}px`;
        hearts.appendChild(h);
    }

    const close = () => {
        wrap.classList.add('is-leaving');
        setTimeout(() => wrap.remove(), 220);
    };
    wrap.querySelector('[data-close]').addEventListener('click', close);
    wrap.querySelector('.hw-match__backdrop').addEventListener('click', close);
    document.body.appendChild(wrap);
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
