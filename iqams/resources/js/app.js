

import Alpine from 'alpinejs';
import { installNavigationFeedback } from './navigation';
import { createPollingTask } from './polling';

window.createIqamsPollingTask = createPollingTask;

window.Alpine = Alpine;

let qrCodeModulePromise = null;

window.ensureIqamsQrCode = () => {
    if (window.QRCode && window.downloadIqamsIdCard && window.printIqamsIdCard) {
        return Promise.resolve();
    }

    qrCodeModulePromise ??= import('./qrcode');

    return qrCodeModulePromise;
};

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

Alpine.data('lookupField', (initialState = {}) => ({
    endpoint: initialState.endpoint,
    search: '',
    selectedId: initialState.selected ? String(initialState.selected) : '',
    selectedLabel: initialState.selectedLabel || 'Selected option',
    options: initialState.selected
        ? [{ id: String(initialState.selected), label: initialState.selectedLabel || 'Selected option' }]
        : [],
    loading: false,
    searched: false,
    controller: null,
    requestId: 0,

    async load(selectedOverride = null) {
        this.controller?.abort();
        this.controller = new AbortController();
        const requestId = ++this.requestId;
        const url = new URL(this.endpoint, window.location.href);
        const selected = selectedOverride || this.$refs.select?.value;
        const selectedOption = selected
            ? this.options.find((option) => String(option.id) === String(selected))
            : null;

        if (selected) this.selectedId = String(selected);

        if (this.search.trim() !== '') {
            url.searchParams.set('search', this.search.trim());
        }
        if (selected) {
            url.searchParams.append('selected[]', selected);
        }

        this.loading = true;
        try {
            const response = await fetch(url, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: this.controller.signal,
            });
            if (!response.ok) throw new Error('Lookup failed');

            const data = await response.json();
            if (requestId === this.requestId) {
                const options = data.data || [];
                if (this.selectedId && !options.some((option) => String(option.id) === this.selectedId)) {
                    options.unshift(selectedOption || { id: this.selectedId, label: this.selectedLabel });
                }
                this.options = options;
                this.searched = true;
            }
        } catch (error) {
            if (error.name !== 'AbortError' && requestId === this.requestId) {
                // Keep the last valid options available when a lookup fails.
                this.searched = true;
            }
        } finally {
            if (requestId === this.requestId) {
                this.loading = false;
            }
        }
    },
}));

Alpine.data('instructorWorkspace', () => ({
    refreshTimer: null,
    poller: null,
    downloadingIdCard: false,
    qrCode: null,

    init() {
        this.poller = createPollingTask((signal) => this.refresh(signal));
        this.poller.start();

        window.ensureIqamsQrCode().then(() => this.renderQrCode()).catch(() => {});
        this.loadQrCode();
    },

    destroy() {
        this.poller?.stop();
    },

    renderQrCode() {
        const qr = this.$root.querySelector('#instructor-qr');
        const value = this.qrCode;

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

    async loadQrCode() {
        const endpoint = this.$root.dataset.idCardUrl;
        if (!endpoint) return;

        try {
            const response = await fetch(endpoint, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) return;
            this.qrCode = (await response.json()).qr_code || null;
            this.renderQrCode();
        } catch {
            // Keep the placeholder when the private ID-card endpoint is unavailable.
        }
    },

    async downloadIdCard() {
        if (this.downloadingIdCard) return;
        this.downloadingIdCard = true;

        try {
            await window.ensureIqamsQrCode();
            await window.downloadIqamsIdCard(this.$root.dataset.idCardUrl);
        } catch (error) {
            window.alert(error.message || 'The ID card could not be downloaded.');
        } finally {
            this.downloadingIdCard = false;
        }
    },

    async refresh(signal) {
        const endpoint = this.$root.dataset.realtimeUrl;
        if (!endpoint) return;

        try {
            const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, cache: 'no-store', signal });
            if (!response.ok) return;

            const data = await response.json();
            const day = data.today;
            const statusBadge = this.$root.querySelector('[data-instructor-status]');
            if (statusBadge) statusBadge.textContent = day.summary_status || day.status;
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
    poller: null,
    requestInFlight: false,

    init() {
        this.poller = createPollingTask((signal) => this.refresh(signal));
        this.poller.start();
    },

    destroy() {
        this.poller?.stop();
        this.refreshTimer = null;
    },

    async refresh(signal) {
        if (this.requestInFlight || !this.$root.dataset.realtimeUrl) return;
        this.requestInFlight = true;
        try {
            const response = await fetch(this.$root.dataset.realtimeUrl, { headers: { Accept: 'application/json' }, cache: 'no-store', signal });
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
        root.querySelector('[data-staff-status]').textContent = data.today.summary_status || data.today.status;
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
    qrCode: null,

    init() {
        this.poller = createPollingTask((signal) => this.refresh(signal));
        this.poller.start();
        window.ensureIqamsQrCode().then(() => this.renderQrCode()).catch(() => {});
        this.loadQrCode();
    },

    destroy() {
        this.poller?.stop();
        this.refreshTimer = null;
    },

    renderQrCode() {
        const target = this.$root.querySelector('#staff-qr');
        const value = this.qrCode;

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

    async loadQrCode() {
        const endpoint = this.$root.dataset.idCardUrl;
        if (!endpoint) return;

        try {
            const response = await fetch(endpoint, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) return;
            this.qrCode = (await response.json()).qr_code || null;
            this.renderQrCode();
        } catch {
            // Keep the placeholder when the private ID-card endpoint is unavailable.
        }
    },
}));

Alpine.data('studentWorkspace', () => pollingWorkspace((root, data) => {
    ['present', 'late', 'absent', 'excused'].forEach((status) => {
        const element = root.querySelector(`[data-student-stat="${status}"]`);
        if (element) element.textContent = data.stats[status];
    });
    const percentage = root.querySelector('[data-student-stat="percentage"]');
    if (percentage && data.summary) percentage.textContent = `${Number(data.summary.percentage || 0).toFixed(2)}%`;
    const detail = root.querySelector('[data-student-stat="detail"]');
    if (detail && data.summary) detail.textContent = `${data.summary.attended} attended of ${data.summary.scheduled} rated sessions · ${data.summary.excluded} excluded`;
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

Alpine.data('attendanceOverview', (series) => ({
    series,
    period: 'semester',
    active: null,
    get points() {
        return this.series[this.period] || [];
    },
    x(index) {
        return this.points.length > 1 ? 58 + (index * 672 / (this.points.length - 1)) : 394;
    },
    y(value) {
        return 24 + ((100 - Number(value || 0)) * 210 / 100);
    },
    get linePoints() {
        return this.points.map((point, index) => `${this.x(index)},${this.y(point.percentage)}`).join(' ');
    },
    get areaPoints() {
        if (!this.points.length) return '';
        return `${this.x(0)},234 ${this.linePoints} ${this.x(this.points.length - 1)},234`;
    },
    format(value) {
        return `${Number(value || 0).toFixed(2).replace(/\.00$/, '')}%`;
    },
}));

Alpine.data('studentQr', () => ({
    downloadingIdCard: false,
    qrCode: null,

    init() {
        window.ensureIqamsQrCode().then(() => this.renderQrCode()).catch(() => {});
        this.loadQrCode();
    },

    destroy() {
    },

    renderQrCode() {
        const target = this.$root.querySelector('#student-qr');
        const value = this.qrCode;
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

    async loadQrCode() {
        const endpoint = this.$root.dataset.idCardUrl;
        if (!endpoint) return;

        try {
            const response = await fetch(endpoint, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                cache: 'no-store',
            });
            if (!response.ok) return;
            this.qrCode = (await response.json()).qr_code || null;
            this.renderQrCode();
        } catch {
            // Keep the placeholder when the private ID-card endpoint is unavailable.
        }
    },

    async downloadIdCard() {
        if (this.downloadingIdCard) return;
        this.downloadingIdCard = true;
        try {
            await window.ensureIqamsQrCode();
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
    downloadEndpoint: '',

    init() {
        this.today = this.$root.dataset.today;
        this.endpoint = this.$root.dataset.attendanceEndpoint;
        this.downloadEndpoint = this.$root.dataset.downloadEndpoint;
    },

    get downloadUrl() {
        if (!this.selectedDay?.schedule_id || !this.selectedDate || !this.downloadEndpoint) return '';

        return `${this.downloadEndpoint.replace('__SCHEDULE__', this.selectedDay.schedule_id)}?date=${encodeURIComponent(this.selectedDate)}`;
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
 * Delayed, non-blocking feedback for native document navigation.
 * Never cover a usable page while waiting for secondary resources.
 */
const pageLoader = (() => {
    const SHOW_DELAY = 200;
    let showTimer = null;
    let resetTimer = null;

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
        window.clearTimeout(resetTimer);
        resetTimer = window.setTimeout(() => {
            stop();
            window.dispatchEvent(new Event('iqams:navigation-cancelled'));
        }, 15000);
    };

    const stop = () => {
        window.clearTimeout(resetTimer);
        window.clearTimeout(showTimer);
        overlay.classList.remove('is-visible', 'is-active');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.removeAttribute('aria-busy');
    };

    return { start, stop };
})();

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

installNavigationFeedback(window, document, pageLoader);
