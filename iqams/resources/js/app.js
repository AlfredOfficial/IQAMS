

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

Alpine.data('schoolEventSelect', (options = []) => ({
    options,
    open: false,
    direction: 'down',
    menuStyle: '',

    toggle() {
        this.open = !this.open;
        if (this.open) this.$nextTick(() => this.positionMenu());
    },

    choose(value) {
        this.$refs.select.value = value;
        this.$refs.select.dispatchEvent(new Event('input', { bubbles: true }));
        this.$refs.select.dispatchEvent(new Event('change', { bubbles: true }));
        this.open = false;
    },

    positionMenu() {
        const rect = this.$refs.button.getBoundingClientRect();
        const itemHeight = 40;
        const menuHeight = Math.min(this.options.length * itemHeight, 240);
        const spaceBelow = window.innerHeight - rect.bottom - 8;
        this.direction = spaceBelow < menuHeight && rect.top - 8 >= menuHeight ? 'up' : 'down';
        const top = this.direction === 'up' ? rect.top - menuHeight : rect.bottom;
        this.menuStyle = `top:${Math.max(8, top)}px;left:${rect.left}px;width:${rect.width}px;max-height:${Math.min(menuHeight, window.innerHeight - 16)}px`;
    },

    init() {
        this.$watch('open', (open) => {
            if (open) this.$nextTick(() => this.positionMenu());
        });
        window.addEventListener('resize', () => this.open && this.positionMenu());
        window.addEventListener('scroll', () => this.open && this.positionMenu(), true);
    },
}));

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
    if (percentage && data.summary) percentage.textContent = `${Number(data.summary.percentage || 0).toFixed(1)}%`;
    const attended = root.querySelector('[data-student-stat="attended-count"]');
    if (attended && data.summary) attended.textContent = data.summary.attended;
    const tableBody = root.querySelector('[data-recent-attendance] tbody');
    if (tableBody) {
        const statusClasses = { present: 'bg-emerald-50 text-emerald-700 ring-emerald-200', late: 'bg-amber-50 text-amber-700 ring-amber-200', absent: 'bg-red-50 text-red-700 ring-red-200', excused: 'bg-sky-50 text-sky-700 ring-sky-200' };
        if (!data.recent.length) {
            const row = document.createElement('tr'); const cell = document.createElement('td'); cell.colSpan = 4; cell.className = 'px-4 py-12 text-center text-sm text-slate-500'; cell.textContent = 'No attendance records yet.'; row.append(cell); tableBody.replaceChildren(row); return;
        }
        tableBody.replaceChildren(...data.recent.map(log => {
            const row = document.createElement('tr'); row.className = 'border-b border-slate-100 text-sm last:border-0';
            const cell = (text, classes = 'px-4 py-3.5 text-slate-600') => { const element = document.createElement('td'); element.className = classes; element.textContent = text; return element; };
            row.append(cell(log.date, 'whitespace-nowrap px-4 py-3.5 text-slate-600'), cell(log.title, 'px-4 py-3.5 font-medium text-[#10294b]'));
            const statusCell = document.createElement('td'); statusCell.className = 'px-4 py-3.5'; const badge = document.createElement('span'); badge.className = `inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-xs font-semibold capitalize ring-1 ring-inset ${statusClasses[log.status] || 'bg-slate-50 text-slate-600 ring-slate-200'}`; const dot = document.createElement('span'); dot.className = 'h-1.5 w-1.5 rounded-full bg-current opacity-70'; badge.append(dot, document.createTextNode(log.status || 'Unknown')); statusCell.append(badge); row.append(statusCell);
            row.append(cell(log.time || '—', 'whitespace-nowrap px-4 py-3.5 text-slate-600')); return row;
        }));
        return;
    }
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
        const detail = document.createElement('p'); detail.className = 'mt-2 text-xs capitalize text-slate-500'; detail.textContent = log.status === 'absent' ? `${log.date} · No scan recorded` : `${log.date} · ${log.time} · ${log.type}`;
        article.append(heading, title, status, detail); return article;
    }));
}));

Alpine.data('attendanceOverview', (series) => ({
    series: series || {},
    period: 'semester',
    active: null,
    init() {
        this.$nextTick(() => {
            const svg = this.$root.querySelector('svg[viewBox="0 0 760 300"]');
            if (!svg) return;
            this.renderChart(svg);
            this.$watch('period', () => this.renderChart(svg));

            const chartWrapper = svg.parentElement;
            const chartFrame = chartWrapper.parentElement;
            chartWrapper.classList.remove('overflow-x-auto');
            chartWrapper.classList.add('chart-wrapper');
            chartWrapper.style.width = '100%';
            chartWrapper.style.maxWidth = '100%';
            chartWrapper.style.height = '300px';
            chartWrapper.style.overflow = 'hidden';
            svg.style.width = '100%';
            svg.style.height = '100%';
            svg.style.minWidth = '0';
            chartFrame.classList.add('attendance-chart-container', 'relative', 'w-full', 'max-w-full');
            chartFrame.style.height = '300px';
            chartFrame.style.overflow = 'hidden';
            const summary = chartFrame.nextElementSibling;
            if (summary) {
                summary.classList.add('attendance-summary');
                summary.style.position = 'relative';
                summary.style.marginTop = '20px';
                summary.style.width = 'auto';
            }
        });
    },
    renderChart(svg) {
        const grid = svg.querySelector('[data-chart-grid]');
        const line = svg.querySelector('[data-chart-line]');
        const pointsGroup = svg.querySelector('[data-chart-points]');
        if (!grid || !line || !pointsGroup) return;

        const ns = 'http://www.w3.org/2000/svg';
        grid.replaceChildren();
        pointsGroup.replaceChildren();
        [100, 75, 50, 25, 0].forEach((value) => {
            const guide = document.createElementNS(ns, 'line');
            guide.setAttribute('x1', '58'); guide.setAttribute('x2', '730');
            guide.setAttribute('y1', this.y(value)); guide.setAttribute('y2', this.y(value));
            guide.setAttribute('stroke', '#e8eef2');
            const label = document.createElementNS(ns, 'text');
            label.setAttribute('x', '47'); label.setAttribute('y', this.y(value) + 4);
            label.setAttribute('text-anchor', 'end'); label.setAttribute('fill', '#526987');
            label.setAttribute('font-size', '12'); label.textContent = `${value}%`;
            grid.append(guide, label);
        });
        line.setAttribute('points', this.linePoints);
        this.points.forEach((point, index) => {
            const group = document.createElementNS(ns, 'g');
            const circle = document.createElementNS(ns, 'circle');
            circle.setAttribute('cx', this.x(index)); circle.setAttribute('cy', this.y(point.percentage));
            circle.setAttribute('r', '5.5'); circle.setAttribute('fill', '#0f9f86');
            circle.setAttribute('stroke', 'white'); circle.setAttribute('stroke-width', '2');
            circle.classList.add('cursor-pointer');
            circle.addEventListener('mouseenter', () => { this.active = point; });
            circle.addEventListener('mouseleave', () => { this.active = null; });
            const title = document.createElementNS(ns, 'title');
            title.textContent = `${point.label}: ${this.format(point.percentage)}`;
            circle.append(title);
            const value = document.createElementNS(ns, 'text');
            value.setAttribute('x', this.x(index)); value.setAttribute('y', this.y(point.percentage) - 14);
            value.setAttribute('text-anchor', 'middle'); value.setAttribute('fill', '#087c6a');
            value.setAttribute('font-size', '13'); value.setAttribute('font-weight', '700');
            value.textContent = this.format(point.percentage);
            const label = document.createElementNS(ns, 'text');
            label.setAttribute('x', this.x(index)); label.setAttribute('y', '265');
            label.setAttribute('text-anchor', 'middle'); label.setAttribute('fill', '#334a68');
            label.setAttribute('font-size', '12'); label.textContent = point.label;
            group.append(circle, value, label);
            pointsGroup.append(group);
        });
    },
    get points() {
        return (this.series[this.period] || []).filter((point) => Number.isFinite(Number(point.percentage)));
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
    target: 75,
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

// Show shared sidebar scrollbars briefly while the user scrolls.
document.querySelectorAll('.app-sidebar-nav, .app-main-scroll').forEach((scrollArea) => {
    let scrollTimer;

    scrollArea.addEventListener('scroll', () => {
        scrollArea.classList.add('is-scrolling');
        window.clearTimeout(scrollTimer);
        scrollTimer = window.setTimeout(() => {
            scrollArea.classList.remove('is-scrolling');
        }, 800);
    }, { passive: true });
});

// Native page navigation rebuilds the sidebar, so keep the user's menu position
// when moving between pages instead of sending them back to the first item.
const sidebarScrollKey = 'iqams.sidebar.scrollTop';
const sidebarNav = document.querySelector('[data-sidebar-nav].app-sidebar-nav');

if (sidebarNav) {
    try {
        const savedScrollTop = Number(sessionStorage.getItem(sidebarScrollKey));
        if (Number.isFinite(savedScrollTop) && savedScrollTop > 0) {
            sidebarNav.scrollTop = savedScrollTop;
        }

        window.addEventListener('pagehide', () => {
            sessionStorage.setItem(sidebarScrollKey, String(sidebarNav.scrollTop));
        });
    } catch {
        // Storage may be unavailable in privacy-restricted browser contexts.
    }
}

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

const requiresPasswordConfirmation = (form) => {
    if (form.hasAttribute('data-password-confirmation-required')) return true;

    const action = new URL(form.action, window.location.href).pathname.replace(/\/$/, '');
    const method = (form.querySelector('input[name="_method"]')?.value || form.method).toUpperCase();
    const resourceMutation = /^\/(departments|courses|instructors|non-teaching-staff|office-units|subjects|sections|students|schedules|attendance-logs|school-events)\/[^/]+$/.test(action)
        && ['PUT', 'PATCH', 'DELETE'].includes(method);

    return resourceMutation
        || /^\/admin\/leave-requests\/[^/]+$/.test(action)
        || /^\/roles\/users\/[^/]+$/.test(action)
        || /^\/users\/[^/]+\/(status|password\/reset)$/.test(action)
        || /^\/school-events\/[^/]+\/(publish|cancel)$/.test(action)
        || action === '/scanner-security/qr/batch'
        || /^\/scanner-security\/(terminals|flags)\/[^/]+$/.test(action)
        || /^\/scanner-security\/users\/[^/]+\/qr\/regenerate$/.test(action);
};

const protectAutoSubmittedSensitiveForms = () => {
    document.querySelectorAll('form').forEach((form) => {
        if (!requiresPasswordConfirmation(form)) return;

        form.setAttribute('data-password-confirmation-required', '');

        // Scanner security's status select uses form.submit(), which bypasses
        // submit events. Route it through requestSubmit() so the modal opens.
        if (/^\/scanner-security\/flags\/[^/]+$/.test(new URL(form.action, window.location.href).pathname)) {
            form.submit = () => form.requestSubmit();
        }
    });
};

protectAutoSubmittedSensitiveForms();

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-logout-confirmed')) {
        return;
    }

    if (form.hasAttribute('data-password-reset-confirmation-required')) {
        event.preventDefault();
        window.dispatchEvent(new CustomEvent('password-reset-confirmation-required', { detail: { form } }));
        return;
    }

    if (requiresPasswordConfirmation(form)) {
        event.preventDefault();
        window.dispatchEvent(new CustomEvent('password-confirmation-required', { detail: { form } }));
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

// Keep the admin shell mounted while navigating between admin pages. The
// server-rendered response is still used, but only its shell content is swapped
// so the sidebar does not visibly reset on every click.
if (document.body.hasAttribute('data-admin-shell')) {
    const navigateAdmin = async (url, addHistory = true) => {
        const destination = new URL(url, window.location.href);
        if (destination.origin !== window.location.origin) return false;

        const currentContent = document.querySelector('#app-content');
        const currentNav = document.querySelector('[data-sidebar-nav].app-sidebar-nav');
        if (!currentContent || !currentNav) return false;

        pageLoader.start();
        window.dispatchEvent(new Event('iqams:navigating'));

        try {
            const response = await fetch(destination.href, {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) throw new Error(`Navigation failed: ${response.status}`);

            const html = await response.text();
            const parsed = new DOMParser().parseFromString(html, 'text/html');
            const nextContent = parsed.querySelector('#app-content');
            const nextNav = parsed.querySelector('[data-sidebar-nav].app-sidebar-nav');
            if (!nextContent || !nextNav) throw new Error('Navigation response has no admin shell');

            const sidebarScrollTop = currentNav.scrollTop;
            Alpine.destroyTree(currentContent);
            Alpine.destroyTree(currentNav);
            currentContent.replaceWith(nextContent);
            currentNav.replaceWith(nextNav);
            nextNav.scrollTop = sidebarScrollTop;
            Alpine.initTree(nextNav);
            Alpine.initTree(nextContent);

            if (parsed.title) document.title = parsed.title;
            if (addHistory) window.history.pushState({}, '', destination.href);
            window.scrollTo(0, 0);
            pageLoader.stop();
            window.dispatchEvent(new Event('iqams:navigation-complete'));
            return true;
        } catch {
            pageLoader.stop();
            return false;
        }
    };

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey
            || event.shiftKey || event.altKey) return;
        const link = event.target.closest?.('a[data-sidebar-link][href]');
        if (!link || (link.target && link.target.toLowerCase() !== '_self') || link.hasAttribute('download')) return;

        const destination = new URL(link.href, window.location.href);
        const current = new URL(window.location.href);
        if (destination.origin !== current.origin || destination.hash
            || destination.href === current.href) return;

        event.preventDefault();
        navigateAdmin(destination.href).then((handled) => {
            if (!handled) window.location.assign(destination.href);
        });
    });

    window.addEventListener('popstate', () => {
        navigateAdmin(window.location.href, false).then((handled) => {
            if (!handled) window.location.reload();
        });
    });
}

// Shared lightweight feedback for login and admin CRUD submit actions.
const setFormSubmitLoading = (form) => {
    const button = form?.querySelector('button[type="submit"]:not([disabled])');
    if (!button || button.classList.contains('crud-submit-loading')) return;

    button.disabled = true;
    button.classList.add('crud-submit-loading');
    button.setAttribute('aria-busy', 'true');
};

window.setIqamsFormSubmitLoading = setFormSubmitLoading;

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;

    if (form.matches('[data-login-form]')) {
        const button = form.querySelector('[data-login-submit]');
        const label = form.querySelector('[data-login-label]');
        if (!button || button.disabled) return;
        button.disabled = true;
        button.classList.add('is-loading');
        button.setAttribute('aria-busy', 'true');
        if (label) label.textContent = 'Signing in…';
        return;
    }

    setFormSubmitLoading(form);
});
