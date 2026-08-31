

import Alpine from 'alpinejs';
import './qrcode';

window.Alpine = Alpine;

Alpine.data('schoolEventsModal', (initialState = {}) => {
    const emptyForm = () => ({
        id: '',
        title: '',
        description: '',
        location: '',
        starts_at: '',
        ends_at: '',
        attendance_mode: 'cancelled',
        target_scope: 'school',
        section_ids: [],
        schedule_ids: [],
    });

    return {
        showModal: initialState.showModal ?? false,
        form: initialState.form ?? emptyForm(),

        get editing() {
            return Boolean(this.form.id);
        },

        get formAction() {
            return this.editing
                ? `${initialState.baseUrl}/${this.form.id}`
                : initialState.storeUrl;
        },

        openCreate() {
            this.form = emptyForm();
            this.showModal = true;
            this.focusTitle();
        },

        openEdit(event) {
            this.form = { ...emptyForm(), ...event };
            this.showModal = true;
            this.focusTitle();
        },

        closeModal() {
            this.showModal = false;
        },

        focusTitle() {
            this.$nextTick(() => this.$root.querySelector('[name="title"]')?.focus());
        },
    };
});

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
    refreshTimer: null,
    qrReadyHandler: null,
    downloadingIdCard: false,

    init() {
        this.refreshTimer = window.setInterval(() => this.refresh(), 3000);

        this.qrReadyHandler = () => this.renderQrCode();

        if (window.QRCode) {
            this.renderQrCode();
        } else {
            window.addEventListener('qrcode:ready', this.qrReadyHandler, { once: true });
        }
    },

    destroy() {
        window.clearInterval(this.refreshTimer);
        window.removeEventListener('qrcode:ready', this.qrReadyHandler);
    },

    renderQrCode() {
        const qr = this.$root.querySelector('#instructor-qr');
        const value = this.$root.dataset.qrValue;

        if (!qr) return;

        if (!value) {
            qr.textContent = 'No QR code assigned';
            return;
        }

        if (!window.QRCode) return;

        qr.replaceChildren();
        new window.QRCode(qr, {
            text: value,
            width: 104,
            height: 104,
            colorDark: '#0f172a',
            colorLight: '#ffffff',
        });
    },

    async downloadIdCard() {
        if (this.downloadingIdCard) return;
        this.downloadingIdCard = true;

        try {
            await window.downloadIqamsIdCard(this.$root.dataset.idCardUrl);
        } catch (error) {
            window.alert(error.message || 'The ID card could not be downloaded.');
        } finally {
            this.downloadingIdCard = false;
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
            const statusBadge = this.$root.querySelector('[data-instructor-status]');
            if (statusBadge) statusBadge.textContent = day.status;
            const next = this.$root.querySelector('[data-instructor-next]');
            if (next) next.textContent = day.next_period?.replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase()) ?? 'Complete';
            const progressCount = this.$root.querySelector('[data-instructor-progress-count]');
            if (progressCount) progressCount.textContent = `${day.completed_periods} of 4 completed`;
            const progressPercent = this.$root.querySelector('[data-instructor-progress-percent]');
            if (progressPercent) progressPercent.textContent = `${day.progress_percentage}%`;
            const progressTrack = this.$root.querySelector('[data-instructor-progress-track]');
            if (progressTrack) progressTrack.setAttribute('aria-valuenow', day.progress_percentage);
            const progressBar = this.$root.querySelector('[data-instructor-progress-bar]');
            if (progressBar) progressBar.style.width = `${day.progress_percentage}%`;

            Object.entries(day.events).forEach(([key, event]) => {
                const time = this.$root.querySelector(`#event-${key}`);
                const status = this.$root.querySelector(`#event-${key}-status`);
                const detail = this.$root.querySelector(`#detail-${key}`);
                if (time) time.textContent = event?.time ?? 'Not Recorded';
                if (status) status.textContent = event?.punctuality ?? 'Not Recorded';
                if (detail) detail.textContent = event?.detail ?? 'Not Yet Recorded';
                const milestone = this.$root.querySelector(`[data-instructor-milestone="${key}"]`);
                if (milestone) {
                    const icon = milestone.querySelector('[data-instructor-milestone-icon]');
                    const label = milestone.querySelector('[data-instructor-milestone-label]');
                    icon.textContent = event ? '✓' : '○';
                    icon.classList.toggle('bg-emerald-500', Boolean(event));
                    icon.classList.toggle('text-white', Boolean(event));
                    icon.classList.toggle('bg-gray-200', !event);
                    icon.classList.toggle('text-gray-500', !event);
                    label.classList.toggle('text-emerald-700', Boolean(event));
                    label.classList.toggle('text-gray-600', !event);
                    milestone.classList.toggle('bg-emerald-50', day.next_period === key);
                }
            });

            const stats = {
                attendance: `${data.totals.percentage}%`,
                present: `${data.totals.presentDays} days`,
                absent: `${data.totals.absentDays} days`,
                hours: `${Math.floor(data.totals.totalMinutes / 60)}h ${data.totals.totalMinutes % 60}m`,
                late: `${data.totals.lateCount} days`,
                early: `${data.totals.earlyOutCount} days`,
                incomplete: `${data.totals.incompleteCount} days`,
                in_progress: `${data.totals.inProgressCount} ${data.totals.inProgressCount === 1 ? 'day' : 'days'}`,
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

const pollingWorkspace = (render) => ({
    refreshTimer: null,
    requestInFlight: false,

    init() {
        this.refreshTimer = window.setInterval(() => this.refresh(), 3000);
    },

    destroy() {
        window.clearInterval(this.refreshTimer);
        this.refreshTimer = null;
    },

    async refresh() {
        if (this.requestInFlight || !this.$root.dataset.realtimeUrl) return;
        this.requestInFlight = true;
        try {
            const response = await fetch(this.$root.dataset.realtimeUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (response.ok) render(this.$root, await response.json());
        } catch {
            // Preserve the last rendered state while the endpoint is unavailable.
        } finally {
            this.requestInFlight = false;
        }
    },
});

Alpine.data('staffWorkspace', () => ({
    ...pollingWorkspace((root, data) => {
        root.querySelector('[data-staff-status]').textContent = data.today.status;
        root.querySelector('[data-staff-next]').textContent = data.today.next_period?.replaceAll('_', ' ').replace(/\b\w/g, char => char.toUpperCase()) ?? 'Complete';
        root.querySelector('[data-staff-progress-count]').textContent = `${data.today.completed_periods} of 4 completed`;
        root.querySelector('[data-staff-progress-percent]').textContent = `${data.today.progress_percentage}%`;
        const progressTrack = root.querySelector('[data-staff-progress-track]');
        progressTrack.setAttribute('aria-valuenow', data.today.progress_percentage);
        root.querySelector('[data-staff-progress-bar]').style.width = `${data.today.progress_percentage}%`;
        Object.entries(data.today.events).forEach(([period, event]) => {
            const milestone = root.querySelector(`[data-staff-milestone="${period}"]`);
            const icon = milestone.querySelector('[data-staff-milestone-icon]');
            const label = milestone.querySelector('[data-staff-milestone-label]');
            icon.textContent = event ? '✓' : '○';
            icon.classList.toggle('bg-emerald-500', Boolean(event));
            icon.classList.toggle('text-white', Boolean(event));
            icon.classList.toggle('bg-gray-200', !event);
            icon.classList.toggle('text-gray-500', !event);
            label.classList.toggle('text-emerald-700', Boolean(event));
            label.classList.toggle('text-gray-600', !event);
            milestone.classList.toggle('bg-emerald-50', data.today.next_period === period);
        });
        Object.entries(data.totals).forEach(([key, value]) => {
            const element = root.querySelector(`[data-staff-stat="${key}"]`);
            if (element) element.textContent = key === 'percentage' ? `${value}%` : value;
        });
        const recent = root.querySelector('[data-staff-recent]');
        recent.replaceChildren(...data.recent.map(log => {
            const article = document.createElement('article');
            article.className = 'flex items-center gap-4 px-5 py-4';
            const body = document.createElement('div');
            body.className = 'min-w-0 flex-1';
            const label = document.createElement('p'); label.className = 'text-sm font-semibold text-gray-800'; label.textContent = log.label;
            const date = document.createElement('p'); date.className = 'text-xs text-gray-500'; date.textContent = log.date;
            const detail = document.createElement('div'); detail.className = 'text-right';
            const time = document.createElement('p'); time.className = 'whitespace-nowrap text-sm font-semibold tabular-nums text-gray-800'; time.textContent = log.time;
            const status = document.createElement('p'); status.className = 'text-xs capitalize text-gray-500'; status.textContent = log.status;
            body.append(label, date); detail.append(time, status); article.append(body, detail); return article;
        }));
    }),
    qrReadyHandler: null,

    init() {
        this.refreshTimer = window.setInterval(() => this.refresh(), 3000);
        this.qrReadyHandler = () => this.renderQrCode();

        if (window.QRCode) {
            this.renderQrCode();
        } else {
            window.addEventListener('qrcode:ready', this.qrReadyHandler, { once: true });
        }
    },

    destroy() {
        window.clearInterval(this.refreshTimer);
        this.refreshTimer = null;
        window.removeEventListener('qrcode:ready', this.qrReadyHandler);
    },

    renderQrCode() {
        const target = this.$root.querySelector('#staff-qr');
        const value = this.$root.dataset.qrValue;

        if (!target) return;
        if (!value) {
            target.textContent = 'No QR code assigned';
            return;
        }
        if (!window.QRCode) return;

        target.replaceChildren();
        new window.QRCode(target, {
            text: value,
            width: 160,
            height: 160,
            colorDark: '#0f172a',
            colorLight: '#ffffff',
        });
    },
}));

Alpine.data('studentWorkspace', () => pollingWorkspace((root, data) => {
    const statValues = root.querySelectorAll('[aria-labelledby="summary-title"] p.mt-2');
    ['present', 'late', 'absent', 'excused'].forEach((status, index) => { if (statValues[index]) statValues[index].textContent = data.stats[status]; });
    const section = [...root.querySelectorAll('section')].find(item => item.querySelector('h2')?.textContent.trim() === 'Recent attendance');
    const recent = section?.querySelector('.border.border-slate-200.bg-white');
    if (!recent) return;
    recent.replaceChildren(...data.recent.map(log => {
        const article = document.createElement('article'); article.className = 'border-b border-slate-100 px-4 py-4 last:border-0';
        const heading = document.createElement('p'); heading.className = 'text-xs font-bold text-teal-800'; heading.textContent = log.code;
        const title = document.createElement('p'); title.className = 'truncate text-sm font-medium text-slate-800'; title.textContent = log.title;
        const status = document.createElement('p'); status.className = 'mt-1 text-xs font-semibold uppercase text-slate-600'; status.textContent = log.status;
        const detail = document.createElement('p'); detail.className = 'mt-2 text-xs capitalize text-slate-500'; detail.textContent = `${log.date} · ${log.time} · ${log.type}`;
        article.append(heading, title, status, detail); return article;
    }));
}));

Alpine.data('studentQr', () => ({
    qrReadyHandler: null,
    downloadingIdCard: false,

    init() {
        this.qrReadyHandler = () => this.renderQrCode();
        if (window.QRCode) {
            this.renderQrCode();
        } else {
            window.addEventListener('qrcode:ready', this.qrReadyHandler, { once: true });
        }
    },

    destroy() {
        window.removeEventListener('qrcode:ready', this.qrReadyHandler);
    },

    renderQrCode() {
        const target = this.$root.querySelector('#student-qr');
        const value = this.$root.dataset.qrValue;
        if (!target) return;
        if (!value) {
            target.textContent = 'No QR code assigned';
            return;
        }
        if (!window.QRCode) return;

        target.replaceChildren();
        new window.QRCode(target, {
            text: value,
            width: 224,
            height: 224,
            colorDark: '#093f3d',
        });
    },

    async downloadIdCard() {
        if (this.downloadingIdCard) return;
        this.downloadingIdCard = true;
        try {
            await window.downloadIqamsIdCard(this.$root.dataset.idCardUrl);
        } catch (error) {
            window.alert(error.message || 'The ID card could not be downloaded.');
        } finally {
            this.downloadingIdCard = false;
        }
    },
}));

Alpine.data('classAttendanceBrowser', () => ({
    selectedGroup: null,
    selectedDay: null,
    selectedDate: '',
    availableDates: [],
    attendance: null,
    loading: false,
    error: '',
    today: '',
    endpoint: '',

    init() {
        this.today = this.$root.dataset.today;
        this.endpoint = this.$root.dataset.attendanceEndpoint;
    },

    get monthLabel() {
        return new Intl.DateTimeFormat('en-US', {
            month: 'long',
            year: 'numeric',
            timeZone: 'UTC',
        }).format(new Date(`${this.today}T00:00:00Z`));
    },

    openGroup(group) {
        this.selectedGroup = group;
        this.attendance = null;
        this.error = '';
        const todayName = new Intl.DateTimeFormat('en-US', {
            weekday: 'long',
            timeZone: 'UTC',
        }).format(new Date(`${this.today}T00:00:00Z`)).toLowerCase();
        this.selectDay(group.days.find((day) => day.name === todayName) || group.days[0]);
        this.$nextTick(() => this.$root.querySelector('section[x-show="selectedGroup"]')?.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
        }));
    },

    selectDay(day) {
        this.selectedDay = day;
        this.attendance = null;
        this.error = '';
        const base = new Date(`${this.today}T00:00:00Z`);
        const year = base.getUTCFullYear();
        const month = base.getUTCMonth();
        this.availableDates = [];

        for (let number = 1; number <= new Date(Date.UTC(year, month + 1, 0)).getUTCDate(); number++) {
            const candidate = new Date(Date.UTC(year, month, number));
            const name = new Intl.DateTimeFormat('en-US', {
                weekday: 'long',
                timeZone: 'UTC',
            }).format(candidate).toLowerCase();

            if (name === day.name) {
                this.availableDates.push({
                    value: candidate.toISOString().slice(0, 10),
                    shortDay: day.label.slice(0, 3),
                    dayNumber: number,
                });
            }
        }

        const defaultDate = [...this.availableDates].reverse().find((date) => date.value <= this.today)
            || this.availableDates[0];
        if (defaultDate) this.selectDate(defaultDate.value);
    },

    async selectDate(date) {
        this.selectedDate = date;
        this.loading = true;
        this.error = '';
        this.attendance = null;

        try {
            const url = `${this.endpoint.replace('__SCHEDULE__', this.selectedDay.schedule_id)}?date=${encodeURIComponent(date)}`;
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Attendance could not be loaded.');
            }
            this.attendance = data;
        } catch (error) {
            this.error = error.message || 'Attendance could not be loaded.';
        } finally {
            this.loading = false;
        }
    },

    statusClass(status) {
        return {
            present: 'bg-emerald-100 text-emerald-700',
            late: 'bg-orange-100 text-orange-700',
            absent: 'bg-rose-100 text-rose-700',
            excused: 'bg-violet-100 text-violet-700',
            pending: 'bg-amber-100 text-amber-700',
        }[status] || 'bg-slate-100 text-slate-600';
    },
}));

Alpine.start();

/**
 * A single delayed loading state for full-page requests and in-app navigation.
 * The interaction shield is active immediately, but the visual treatment waits
 * briefly so quick responses do not flash a spinner.
 */
const pageLoader = (() => {
    const SHOW_DELAY = 200;
    let showTimer = null;

    const overlay = document.createElement('div');
    overlay.id = 'global-page-loader';
    overlay.className = 'global-page-loader';
    overlay.setAttribute('aria-hidden', 'true');
    overlay.innerHTML = `
        <div class="global-page-loader__indicator" role="status" aria-live="polite">
            <span class="global-page-loader__spinner" aria-hidden="true"></span>
            <span class="global-page-loader__label">Loading…</span>
        </div>
    `;
    document.body.append(overlay);

    const start = () => {
        window.clearTimeout(showTimer);
        overlay.classList.add('is-active');
        document.body.setAttribute('aria-busy', 'true');

        showTimer = window.setTimeout(() => {
            overlay.classList.add('is-visible');
            overlay.setAttribute('aria-hidden', 'false');
        }, SHOW_DELAY);
    };

    const stop = () => {
        window.clearTimeout(showTimer);
        overlay.classList.remove('is-visible', 'is-active');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.removeAttribute('aria-busy');
    };

    return { start, stop };
})();

// Cover the remainder of a genuinely slow initial document/resource load.
if (document.readyState !== 'complete') {
    pageLoader.start();
    window.addEventListener('load', pageLoader.stop, { once: true });
}

// Restore pages returned from the browser's back-forward cache without a stale overlay.
window.addEventListener('pageshow', pageLoader.stop);

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
    pageLoader.start();

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
        pageLoader.stop();
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

document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    const link = event.target.closest('a[href]');
    if (!link || (link.target && link.target.toLowerCase() !== '_self') || link.hasAttribute('download')) {
        return;
    }

    const destination = new URL(link.href, window.location.href);
    if (!['http:', 'https:'].includes(destination.protocol)) {
        return;
    }

    const currentUrl = new URL(window.location.href);
    const isSameDocumentHash = destination.origin === currentUrl.origin
        && destination.pathname === currentUrl.pathname
        && destination.search === currentUrl.search
        && destination.hash;

    if (!isSameDocumentHash) {
        pageLoader.start();
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-logout-confirmed')) {
        return;
    }

    const action = new URL(form.action, window.location.href);
    const isLogoutRequest = form.method.toLowerCase() === 'post' && /\/logout\/?$/.test(action.pathname);

    if (isLogoutRequest) {
        event.preventDefault();
        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'confirm-logout' }));
    }
});

document.addEventListener('submit', (event) => {
    const form = event.target;
    const navigatesCurrentPage = form instanceof HTMLFormElement
        && (!form.target || form.target.toLowerCase() === '_self');

    if (!event.defaultPrevented && navigatesCurrentPage && form.checkValidity()) {
        pageLoader.start();
    }
});

window.addEventListener('popstate', () => navigateInApp(window.location.href, false));
