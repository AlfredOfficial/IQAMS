

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('toastNotifications', (initialNotifications = []) => ({
    toasts: [],
    nextId: 1,

    init() {
        initialNotifications.forEach((notification) => this.add(notification));
    },

    add(notification) {
        const message = typeof notification === 'string' ? notification : notification?.message;

        if (!message) return;

        const toast = {
            id: this.nextId++,
            title: notification?.title || 'Success',
            message,
            visible: true,
        };

        this.toasts.push(toast);
        window.setTimeout(() => this.dismiss(toast.id), 2500);
    },

    dismiss(id) {
        const toast = this.toasts.find((item) => item.id === id);
        if (!toast) return;

        toast.visible = false;
        window.setTimeout(() => {
            this.toasts = this.toasts.filter((item) => item.id !== id);
        }, 300);
    },
}));

Alpine.data('instructorWorkspace', () => ({
    clockTimer: null,
    refreshTimer: null,

    init() {
        this.updateClock();
        this.clockTimer = window.setInterval(() => this.updateClock(), 1000);
        this.refreshTimer = window.setInterval(() => this.refresh(), 3000);

        const qr = this.$root.querySelector('#instructor-qr');
        const value = this.$root.dataset.qrValue;

        if (qr && value && window.QRCode) {
            qr.replaceChildren();
            new window.QRCode(qr, {
                text: value,
                width: 104,
                height: 104,
                colorDark: '#0f172a',
                colorLight: '#ffffff',
            });
        }
    },

    destroy() {
        window.clearInterval(this.clockTimer);
        window.clearInterval(this.refreshTimer);
    },

    updateClock() {
        const clock = document.getElementById('live-clock');
        if (clock) {
            clock.textContent = new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
        }
    },

    async refresh() {
        const endpoint = this.$root.dataset.realtimeUrl;
        if (!endpoint) return;

        try {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) return;

            const data = await response.json();
            const day = data.today;
            const hero = this.$root.querySelector('#hero-status');
            if (hero) hero.textContent = `✓ ${day.status}`;

            Object.entries(day.events).forEach(([key, event]) => {
                const time = this.$root.querySelector(`#event-${key}`);
                const status = this.$root.querySelector(`#event-${key}-status`);
                const detail = this.$root.querySelector(`#detail-${key}`);
                if (time) time.textContent = event?.time ?? 'Not Recorded';
                if (status) status.textContent = event?.punctuality ?? 'Not Recorded';
                if (detail) detail.textContent = event?.detail ?? 'Not Yet Recorded';
            });

            const stats = {
                attendance: `${data.totals.percentage}%`,
                present: `${data.totals.presentDays} days`,
                absent: `${data.totals.absentDays} days`,
                hours: `${Math.floor(data.totals.totalMinutes / 60)}h ${data.totals.totalMinutes % 60}m`,
                late: `${data.totals.lateCount} days`,
                early: `${data.totals.earlyOutCount} days`,
                incomplete: `${data.totals.incompleteCount} days`,
            };

            Object.entries(stats).forEach(([key, value]) => {
                const element = this.$root.querySelector(`[data-stat="${key}"]`);
                if (element) element.textContent = value;
            });
        } catch {
            // Keep the last rendered server state when polling is unavailable.
        }
    },
}));

Alpine.start();

/**
 * Keep the navigation shell in place when a sidebar link is selected.
 * Laravel still renders the destination server-side; only the content area and
 * navigation markup are swapped in the browser.
 */
const canUseInAppNavigation = (link, event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return false;
    }

    if (link.target || link.hasAttribute('download')) {
        return false;
    }

    const destination = new URL(link.href, window.location.href);

    return destination.origin === window.location.origin && destination.pathname !== window.location.pathname;
};

const replaceNavigation = (documentResponse) => {
    const currentNavs = [...document.querySelectorAll('[data-sidebar-nav]')];
    const nextNavs = [...documentResponse.querySelectorAll('[data-sidebar-nav]')];

    currentNavs.forEach((currentNav, index) => {
        const nextNav = nextNavs[index];

        if (!nextNav) {
            return;
        }

        const scrollTop = currentNav.scrollTop;
        Alpine.destroyTree(currentNav);
        currentNav.replaceWith(nextNav);
        Alpine.initTree(nextNav);
        nextNav.scrollTop = scrollTop;
    });
};

const navigateInApp = async (url, pushState = true) => {
    try {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok || response.redirected) {
            window.location.assign(url);
            return;
        }

        const responseDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
        const currentContent = document.querySelector('#app-content');
        const nextContent = responseDocument.querySelector('#app-content');

        if (!currentContent || !nextContent) {
            window.location.assign(url);
            return;
        }

        Alpine.destroyTree(currentContent);
        currentContent.replaceWith(nextContent);
        Alpine.initTree(nextContent);
        replaceNavigation(responseDocument);
        document.title = responseDocument.title;

        if (pushState) {
            window.history.pushState({}, '', url);
        }

        window.dispatchEvent(new CustomEvent('spa-navigated'));
        window.scrollTo(0, 0);
    } catch {
        window.location.assign(url);
    }
};

document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-sidebar-link]');

    if (!link || !canUseInAppNavigation(link, event)) {
        return;
    }

    event.preventDefault();
    navigateInApp(link.href);
});

window.addEventListener('popstate', () => navigateInApp(window.location.href, false));
