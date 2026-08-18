<x-app-layout>
    <div class="min-h-screen bg-slate-50 py-6"
         x-data="{
            data: {{ Illuminate\Support\Js::from($dashboardData) }},
            endpoint: {{ Illuminate\Support\Js::from(route('admin.dashboard.realtime')) }},
            loading: false, online: true, lastId: {{ $dashboardData['scans'][0]['id'] ?? 0 }},
            confirmation: null, confirmTimer: null, timer: null, clockTimer: null,
            clockDate: '', clockTime: '', page: 1, perPage: 10,
            filters: { search: '', role: '', department: '', section: '', subject: '', status: '', period: 'today' },
            init() {
                this.tick();
                this.clockTimer = setInterval(() => this.tick(), 1000);
                this.timer = setInterval(() => this.refresh(), 4000);
            },
            tick() {
                const now = new Date();
                this.clockDate = now.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
                this.clockTime = now.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', second: '2-digit' });
            },
            async refresh() {
                if (this.loading || document.hidden) return;
                this.loading = true;
                try {
                    const response = await fetch(this.endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    if (!response.ok) throw new Error('Unable to refresh');
                    const fresh = await response.json();
                    const newest = fresh.scans[0];
                    if (newest && newest.id > this.lastId) {
                        this.lastId = newest.id;
                        this.confirmation = newest;
                        clearTimeout(this.confirmTimer);
                        this.confirmTimer = setTimeout(() => this.confirmation = null, 7000);
                    }
                    this.data = fresh; this.online = true;
                } catch (error) { this.online = false; }
                finally { this.loading = false; }
            },
            get filteredScans() {
                const query = this.filters.search.toLowerCase().trim();
                const now = new Date();
                return this.data.scans.filter(scan => {
                    const haystack = [scan.identifier, scan.name, scan.role, scan.group, scan.subject, scan.subject_code].filter(Boolean).join(' ').toLowerCase();
                    const date = new Date(scan.timestamp);
                    const periodMatch = this.filters.period === 'all' ||
                        (this.filters.period === 'today' && date.toDateString() === now.toDateString()) ||
                        (this.filters.period === 'week' && (now - date) <= 7 * 86400000);
                    return (!query || haystack.includes(query)) && (!this.filters.role || scan.role_key === this.filters.role) &&
                        (!this.filters.department || scan.department === this.filters.department) &&
                        (!this.filters.section || scan.section === this.filters.section) &&
                        (!this.filters.subject || scan.subject === this.filters.subject) &&
                        (!this.filters.status || scan.status === this.filters.status) && periodMatch;
                });
            },
            get pageCount() { return Math.max(1, Math.ceil(this.filteredScans.length / this.perPage)); },
            get pagedScans() { this.page = Math.min(this.page, this.pageCount); return this.filteredScans.slice((this.page - 1) * this.perPage, this.page * this.perPage); },
            resetFilters() { this.filters = { search: '', role: '', department: '', section: '', subject: '', status: '', period: 'today' }; this.page = 1; },
            roleColor(role) { return { student: 'bg-sky-50 text-sky-700', instructor: 'bg-violet-50 text-violet-700', staff: 'bg-amber-50 text-amber-700' }[role] || 'bg-slate-100 text-slate-600'; },
            statusColor(status) { return { present: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', late: 'bg-amber-50 text-amber-700 ring-amber-600/20', incomplete: 'bg-red-50 text-red-700 ring-red-600/20', absent: 'bg-red-50 text-red-700 ring-red-600/20', excused: 'bg-slate-100 text-slate-600 ring-slate-500/20' }[status] || 'bg-slate-100 text-slate-600 ring-slate-500/20'; },
            actionColor(action) { return action === 'time_out' ? 'bg-orange-50 text-orange-700 ring-orange-600/20' : 'bg-blue-50 text-blue-700 ring-blue-600/20'; },
            max(items) { return Math.max(1, ...items.map(item => item.value)); },
            points(items) { const max = this.max(items); const width = 560; const height = 120; return items.map((item, index) => `${items.length === 1 ? 0 : index * width / (items.length - 1)},${height - (item.value / max * 105)}`).join(' '); }
         }"
         @keydown.escape.window="confirmation = null">
        <header class="-mt-6 mb-6 bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-blue-600">IQAMS Control Center</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">Attendance Dashboard</h1>
                        <p class="mt-1 text-sm text-slate-500">Live college-wide attendance monitoring and analytics</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="relative hidden md:block">
                            <svg class="pointer-events-none absolute left-3 top-2.5 h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35m2.1-5.4a7.5 7.5 0 1 1-15 0 7.5 7.5 0 0 1 15 0Z"/></svg>
                            <input form="attendance-filters" name="search" x-model="filters.search" type="search" placeholder="Search attendance..." class="w-64 rounded-xl border-slate-200 bg-slate-50 py-2 pl-10 pr-4 text-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <x-leave-notification-bell />
                        <div class="min-w-44 rounded-xl border border-slate-200 bg-white px-4 py-2 text-right shadow-sm">
                            <p class="text-sm font-semibold text-slate-800" x-text="clockDate"></p>
                            <p class="text-xs text-slate-500" x-text="clockTime"></p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2 text-xs font-medium" :class="online ? 'text-emerald-700' : 'text-red-600'">
                    <span class="relative flex h-2.5 w-2.5"><span x-show="online" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full" :class="online ? 'bg-emerald-500' : 'bg-red-500'"></span></span>
                    <span x-text="online ? 'Live monitoring active' : 'Connection interrupted — retrying'"></span>
                </div>
                <span class="text-xs text-slate-400" x-text="loading ? 'Updating…' : 'Auto-refreshes every 4 seconds'"></span>
            </div>

            {{-- Summary cards --}}
            <section class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-8">
                @php
                    $cards = [
                        ['total_scanned', 'Total scanned', 'bg-blue-50 text-blue-600', 'M7 7h10M7 12h10M7 17h6'],
                        ['students', 'Students', 'bg-sky-50 text-sky-600', 'M12 14 3 9l9-5 9 5-9 5Zm0 0v6'],
                        ['instructors', 'Teaching', 'bg-violet-50 text-violet-600', 'M4 19.5V6a2 2 0 0 1 2-2h12v15.5M6 16h12'],
                        ['staff', 'Non-teaching', 'bg-amber-50 text-amber-600', 'M9 6h6m-8 4h10M5 20h14V6a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v14Z'],
                        ['present', 'Currently present', 'bg-emerald-50 text-emerald-600', 'm5 12 4 4L19 6'],
                        ['late', 'Late users', 'bg-yellow-50 text-yellow-600', 'M12 7v5l3 2m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
                        ['missing_timeout', 'Missing time-out', 'bg-orange-50 text-orange-600', 'M12 8v5m0 3h.01M10.3 3.7 2 18h20L13.7 3.7a2 2 0 0 0-3.4 0Z'],
                        ['incomplete', 'Incomplete', 'bg-red-50 text-red-600', 'M6 18 18 6M6 6l12 12'],
                    ];
                @endphp
                @foreach ($cards as [$key, $label, $colorClasses, $path])
                    <article class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                        <div class="mb-3 flex items-start justify-between">
                            <span class="rounded-xl p-2 {{ $colorClasses }}"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $path }}"/></svg></span>
                            @if ($key === 'total_scanned')<span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">TODAY</span>@endif
                        </div>
                        <p class="text-2xl font-bold tabular-nums text-slate-900" x-text="data.stats.{{ $key }}"></p>
                        <p class="mt-1 text-xs font-medium leading-tight text-slate-500">{{ $label }}</p>
                    </article>
                @endforeach
            </section>

            <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(320px,.8fr)]">
                {{-- Live activity --}}
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                        <div><h2 class="font-semibold text-slate-900">Live QR Scan Activity</h2><p class="mt-0.5 text-xs text-slate-500">Newest successful scan appears first</p></div>
                        <span class="flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700"><span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"></span>LIVE</span>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <template x-for="scan in data.scans.slice(0, 8)" :key="scan.id">
                            <div class="grid gap-3 px-5 py-4 transition hover:bg-slate-50 md:grid-cols-[minmax(230px,1.4fr)_1fr_1fr_auto] md:items-center">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="h-11 w-11 shrink-0 overflow-hidden rounded-xl bg-gradient-to-br from-blue-100 to-indigo-100">
                                        <img x-show="scan.avatar" :src="scan.avatar" class="h-full w-full object-cover" alt="">
                                        <span x-show="!scan.avatar" class="flex h-full w-full items-center justify-center text-sm font-bold text-blue-700" x-text="scan.initials"></span>
                                    </div>
                                    <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900" x-text="scan.name"></p><p class="text-xs text-slate-500"><span x-text="scan.identifier"></span> · <span x-text="scan.role"></span></p></div>
                                </div>
                                <div class="min-w-0"><p class="truncate text-sm font-medium text-slate-700" x-text="scan.group"></p><p class="truncate text-xs text-slate-500" x-text="scan.subject || 'General attendance'"></p></div>
                                <div><p class="text-sm font-semibold tabular-nums text-slate-800" x-text="scan.time"></p><p class="text-xs text-slate-400" x-text="scan.location || 'Main scanner'"></p></div>
                                <div class="flex gap-2 md:justify-end"><span class="rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset" :class="actionColor(scan.attendance_type)" x-text="scan.attendance_label"></span><span class="rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset" :class="statusColor(scan.status)" x-text="scan.status_label"></span></div>
                            </div>
                        </template>
                        <div x-show="!data.scans.length" class="px-6 py-16 text-center text-sm text-slate-400">Waiting for today's first QR scan…</div>
                    </div>
                </div>

                {{-- Today overview --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="font-semibold text-slate-900">Today's Attendance Overview</h2>
                    <p class="mt-1 text-xs text-slate-500">Unique people recorded by user type</p>
                    <div class="mt-5 space-y-6">
                        <template x-for="row in data.overview" :key="row.label">
                            <div>
                                <div class="mb-2 flex items-end justify-between"><div><p class="text-sm font-semibold text-slate-800" x-text="row.label"></p><p class="mt-0.5 text-xs text-slate-500"><span x-text="row.scanned"></span> of <span x-text="row.total"></span> scanned</p></div><span class="text-lg font-bold text-blue-700" x-text="row.percentage + '%' "></span></div>
                                <div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-gradient-to-r from-blue-600 to-cyan-500 transition-all duration-500" :style="`width:${row.percentage}%`"></div></div>
                                <div class="mt-2 flex justify-between text-xs"><span class="text-emerald-700"><span x-text="row.primary_label"></span>: <b x-text="row.primary"></b></span><span class="text-amber-700"><span x-text="row.secondary_label"></span>: <b x-text="row.secondary"></b></span></div>
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            {{-- Analytics --}}
            <section class="mt-6 grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
                    <div class="flex items-center justify-between"><div><h3 class="text-sm font-semibold text-slate-900">Daily Scan Trend</h3><p class="text-xs text-slate-500">Scans by hour today</p></div><span class="text-xs font-medium text-blue-600">Live</span></div>
                    <div class="mt-5 h-40 w-full"><svg class="h-full w-full overflow-visible" viewBox="0 0 560 140" preserveAspectRatio="none"><defs><linearGradient id="trendFill" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3b82f6" stop-opacity=".28"/><stop offset="1" stop-color="#3b82f6" stop-opacity="0"/></linearGradient></defs><line x1="0" y1="120" x2="560" y2="120" stroke="#e2e8f0"/><polygon :points="`0,120 ${points(data.charts.hourly)} 560,120`" fill="url(#trendFill)"/><polyline :points="points(data.charts.hourly)" fill="none" stroke="#2563eb" stroke-width="3" vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                    <div class="flex justify-between text-[10px] text-slate-400"><template x-for="(item, i) in data.charts.hourly"><span x-show="i % 2 === 0" x-text="item.label"></span></template></div>
                </article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-sm font-semibold text-slate-900">Attendance by User Type</h3><p class="text-xs text-slate-500">Today's scan distribution</p><div class="mt-5 space-y-4"><template x-for="(item, i) in data.charts.roles"><div><div class="mb-1 flex justify-between text-xs"><span class="text-slate-600" x-text="item.label"></span><b class="text-slate-800" x-text="item.value"></b></div><div class="h-2 rounded-full bg-slate-100"><div class="h-2 rounded-full" :class="['bg-blue-600','bg-violet-500','bg-amber-500'][i]" :style="`width:${item.value / max(data.charts.roles) * 100}%`"></div></div></div></template></div></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-sm font-semibold text-slate-900">Attendance Status</h3><p class="text-xs text-slate-500">Current status breakdown</p><div class="mt-5 grid grid-cols-2 gap-3"><template x-for="(item, i) in data.charts.statuses"><div class="rounded-xl bg-slate-50 p-3"><span class="mb-2 block h-2 w-2 rounded-full" :class="['bg-emerald-500','bg-amber-500','bg-red-500','bg-slate-400'][i]"></span><p class="text-xl font-bold text-slate-900" x-text="item.value"></p><p class="text-[11px] text-slate-500" x-text="item.label"></p></div></template></div></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-sm font-semibold text-slate-900">By Department</h3><p class="text-xs text-slate-500">Top teaching departments</p><div class="mt-4 space-y-2"><template x-for="item in data.charts.departments"><div class="flex items-center gap-2"><span class="w-20 truncate text-xs text-slate-600" x-text="item.label"></span><div class="h-2 flex-1 rounded bg-slate-100"><div class="h-2 rounded bg-indigo-500" :style="`width:${item.value / max(data.charts.departments) * 100}%`"></div></div><b class="w-6 text-right text-xs" x-text="item.value"></b></div></template><p x-show="!data.charts.departments.length" class="py-8 text-center text-xs text-slate-400">No data today</p></div></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-sm font-semibold text-slate-900">Student Scans by Subject</h3><p class="text-xs text-slate-500">Most active subjects today</p><div class="mt-4 space-y-2"><template x-for="item in data.charts.subjects"><div class="flex items-center gap-2"><span class="w-20 truncate text-xs text-slate-600" x-text="item.label"></span><div class="h-2 flex-1 rounded bg-slate-100"><div class="h-2 rounded bg-cyan-500" :style="`width:${item.value / max(data.charts.subjects) * 100}%`"></div></div><b class="w-6 text-right text-xs" x-text="item.value"></b></div></template><p x-show="!data.charts.subjects.length" class="py-8 text-center text-xs text-slate-400">No data today</p></div></article>
                <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2"><h3 class="text-sm font-semibold text-slate-900">Weekly Attendance Trend</h3><p class="text-xs text-slate-500">Scan activity for the current week</p><div class="mt-5 flex h-32 items-end gap-3"><template x-for="item in data.charts.weekly"><div class="flex h-full flex-1 flex-col justify-end text-center"><span class="mb-1 text-xs font-semibold text-slate-700" x-text="item.value"></span><div class="mx-auto w-full max-w-10 rounded-t-md bg-gradient-to-t from-blue-600 to-cyan-400 transition-all" :style="`height:${Math.max(4, item.value / max(data.charts.weekly) * 90)}px`"></div><span class="mt-2 text-[11px] text-slate-500" x-text="item.label"></span></div></template></div></article>
            </section>

            {{-- Detailed table --}}
            <section class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 p-5"><div class="mb-4 flex items-center justify-between"><div><h2 class="font-semibold text-slate-900">Attendance Records</h2><p class="text-xs text-slate-500">Detailed, searchable scan history</p></div><span class="text-xs text-slate-500"><b x-text="filteredScans.length"></b> records</span></div>
                    <form id="attendance-filters" @submit.prevent class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
                        <input x-model="filters.search" @input="page=1" type="search" placeholder="Name or ID..." class="rounded-lg border-slate-200 text-xs focus:border-blue-500 focus:ring-blue-500 xl:hidden">
                        <select x-model="filters.role" @change="page=1" class="rounded-lg border-slate-200 text-xs"><option value="">All roles</option><option value="student">Students</option><option value="instructor">Teaching</option><option value="staff">Non-teaching</option></select>
                        <select x-model="filters.department" @change="page=1" class="rounded-lg border-slate-200 text-xs"><option value="">All departments</option><template x-for="item in data.filters.departments"><option :value="item" x-text="item"></option></template></select>
                        <select x-model="filters.section" @change="page=1" class="rounded-lg border-slate-200 text-xs"><option value="">All sections</option><template x-for="item in data.filters.sections"><option :value="item" x-text="item"></option></template></select>
                        <select x-model="filters.subject" @change="page=1" class="rounded-lg border-slate-200 text-xs"><option value="">All subjects</option><template x-for="item in data.filters.subjects"><option :value="item" x-text="item"></option></template></select>
                        <select x-model="filters.status" @change="page=1" class="rounded-lg border-slate-200 text-xs"><option value="">All statuses</option><option value="present">Present</option><option value="late">Late</option><option value="absent">Missing</option><option value="excused">Excused</option></select>
                        <select x-model="filters.period" @change="page=1" class="rounded-lg border-slate-200 text-xs"><option value="today">Today</option><option value="week">Last 7 days</option><option value="all">All loaded</option></select>
                        <button type="button" @click="resetFilters" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50">Reset</button>
                    </form>
                </div>
                <div class="overflow-x-auto"><table class="min-w-[1250px] w-full text-left text-xs"><thead class="bg-slate-50 uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Photo</th><th class="px-3 py-3">User ID</th><th class="px-3 py-3">Name</th><th class="px-3 py-3">Role</th><th class="px-3 py-3">Section / Department</th><th class="px-3 py-3">Subject</th><th class="px-3 py-3">Date</th><th class="px-3 py-3">Time scanned</th><th class="px-3 py-3">Type</th><th class="px-3 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr></thead>
                    <tbody class="divide-y divide-slate-100"><template x-for="scan in pagedScans" :key="scan.id"><tr class="hover:bg-slate-50"><td class="px-5 py-3"><div class="h-9 w-9 overflow-hidden rounded-lg bg-blue-50"><img x-show="scan.avatar" :src="scan.avatar" class="h-full w-full object-cover" alt=""><span x-show="!scan.avatar" class="flex h-full items-center justify-center font-bold text-blue-700" x-text="scan.initials"></span></div></td><td class="px-3 py-3 font-mono font-medium text-slate-700" x-text="scan.identifier"></td><td class="px-3 py-3 font-semibold text-slate-800" x-text="scan.name"></td><td class="px-3 py-3"><span class="rounded-full px-2 py-1 font-medium" :class="roleColor(scan.role_key)" x-text="scan.role"></span></td><td class="px-3 py-3 text-slate-600" x-text="scan.group"></td><td class="px-3 py-3 text-slate-600" x-text="scan.subject || '—'"></td><td class="px-3 py-3 text-slate-500" x-text="scan.date"></td><td class="px-3 py-3 font-medium tabular-nums text-slate-700" x-text="scan.time"></td><td class="px-3 py-3"><span class="rounded-full px-2 py-1 font-medium ring-1 ring-inset" :class="actionColor(scan.attendance_type)" x-text="scan.attendance_label"></span></td><td class="px-3 py-3"><span class="rounded-full px-2 py-1 font-semibold ring-1 ring-inset" :class="statusColor(scan.status)" x-text="scan.status_label"></span></td><td class="px-5 py-3 text-right"><a :href="`{{ url('attendance-logs') }}?search=${encodeURIComponent(scan.identifier)}`" class="font-semibold text-blue-600 hover:text-blue-800">View</a></td></tr></template><tr x-show="!pagedScans.length"><td colspan="11" class="px-6 py-12 text-center text-sm text-slate-400">No attendance records match these filters.</td></tr></tbody></table></div>
                <div class="flex items-center justify-between border-t border-slate-100 px-5 py-4"><p class="text-xs text-slate-500">Page <b x-text="page"></b> of <b x-text="pageCount"></b></p><div class="flex gap-2"><button type="button" @click="page--" :disabled="page <= 1" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium disabled:opacity-40">Previous</button><button type="button" @click="page++" :disabled="page >= pageCount" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium disabled:opacity-40">Next</button></div></div>
            </section>
        </div>

        {{-- Real-time scan confirmation --}}
        <div x-show="confirmation" x-transition.opacity x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm">
            <div @click.outside="confirmation = null" x-transition class="relative w-full max-w-lg overflow-hidden rounded-3xl bg-white shadow-2xl">
                <div class="bg-gradient-to-r from-emerald-600 to-teal-500 px-6 py-5 text-center text-white"><div class="mx-auto mb-2 flex h-11 w-11 items-center justify-center rounded-full bg-white/20"><svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="m5 12 4 4L19 6"/></svg></div><p class="text-xs font-bold tracking-[.2em]">QR SCAN SUCCESSFUL</p></div>
                <template x-if="confirmation"><div class="p-7 text-center"><div class="mx-auto h-24 w-24 overflow-hidden rounded-2xl bg-blue-50 ring-4 ring-white shadow-lg"><img x-show="confirmation.avatar" :src="confirmation.avatar" class="h-full w-full object-cover" alt=""><span x-show="!confirmation.avatar" class="flex h-full items-center justify-center text-2xl font-bold text-blue-700" x-text="confirmation.initials"></span></div><h3 class="mt-4 text-xl font-bold text-slate-900" x-text="confirmation.name"></h3><p class="font-mono text-sm text-slate-500" x-text="confirmation.identifier"></p><span class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="roleColor(confirmation.role_key)" x-text="confirmation.role"></span><div class="mt-5 grid grid-cols-2 gap-3 rounded-2xl bg-slate-50 p-4 text-left text-sm"><div><p class="text-xs text-slate-400">Section / Department</p><p class="font-semibold text-slate-700" x-text="confirmation.group"></p></div><div><p class="text-xs text-slate-400">Course</p><p class="font-semibold text-slate-700" x-text="confirmation.course || '—'"></p></div><div class="col-span-2"><p class="text-xs text-slate-400">Subject</p><p class="font-semibold text-slate-700" x-text="confirmation.subject || 'General attendance'"></p></div></div><div class="mt-5 flex items-center justify-center gap-3"><div><p class="text-xs text-slate-400" x-text="confirmation.date"></p><p class="text-2xl font-bold tabular-nums text-slate-900" x-text="confirmation.time"></p></div><span class="rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset" :class="actionColor(confirmation.attendance_type)" x-text="confirmation.attendance_label"></span><span class="rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset" :class="statusColor(confirmation.status)" x-text="confirmation.status_label"></span></div><button type="button" @click="confirmation = null" class="mt-6 text-xs font-semibold text-slate-400 hover:text-slate-700">Dismiss confirmation</button></div></template>
            </div>
        </div>
    </div>
</x-app-layout>
