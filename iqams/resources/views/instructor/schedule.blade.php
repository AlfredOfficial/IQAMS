<x-instructor-layout title="My Teaching Schedule">
<div x-data="classAttendanceBrowser()" class="mx-auto max-w-[1500px] space-y-5">
    <div>
        <h2 class="text-xl font-extrabold text-[#10294b]">My Teaching Schedule</h2>
        <p class="mt-1 text-sm text-slate-500">Select a class, weekday, and actual date to review student attendance.</p>
    </div>

    @if($scheduleGroups->isEmpty())
        <section class="rounded-2xl border border-slate-200 bg-white p-10 text-center shadow-sm">
            <p class="font-semibold text-slate-700">No teaching schedules assigned.</p>
            <p class="mt-1 text-sm text-slate-500">Assigned classes will appear here.</p>
        </section>
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($scheduleGroups as $group)
                <button type="button" @click="openGroup(@js($group))"
                    :class="selectedGroup?.key === @js($group['key']) ? 'border-blue-500 ring-2 ring-blue-100' : 'border-slate-200 hover:border-blue-300'"
                    class="rounded-2xl border bg-white p-5 text-left shadow-sm transition">
                    <div class="flex items-start justify-between gap-4">
                        <div><p class="text-lg font-extrabold text-[#10294b]">{{ $group['subject_code'] }}</p><p class="mt-1 text-sm font-semibold text-slate-700">{{ $group['subject_name'] }}</p></div>
                        <span class="rounded-lg bg-blue-50 px-3 py-1.5 text-sm font-extrabold text-blue-700">{{ $group['days_label'] }}</span>
                    </div>
                    <div class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-sm text-slate-600">
                        <p class="font-semibold text-slate-800">{{ $group['start_time'] }} - {{ $group['end_time'] }}</p>
                        <p>{{ $group['section'] }} <span class="mx-2 text-slate-300">•</span> Room {{ $group['room'] }}</p>
                        <p class="text-xs text-slate-400">{{ collect($group['days'])->pluck('label')->implode(' · ') }}</p>
                    </div>
                </button>
            @endforeach
        </div>

        <section x-show="selectedGroup" x-cloak class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-5 sm:p-6">
                <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                    <div><p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Selected class</p><h3 class="mt-1 text-xl font-extrabold text-[#10294b]" x-text="selectedGroup ? selectedGroup.subject_code + ' · ' + selectedGroup.subject_name : ''"></h3><p class="mt-1 text-sm text-slate-500" x-text="selectedGroup ? selectedGroup.section + ' · Room ' + selectedGroup.room : ''"></p></div>
                    <p class="text-sm font-semibold text-slate-700" x-text="selectedGroup ? selectedGroup.start_time + ' - ' + selectedGroup.end_time : ''"></p>
                </div>

                <div class="mt-6">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Class day</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <template x-for="day in selectedGroup?.days || []" :key="day.schedule_id"><button type="button" @click="selectDay(day)" :class="selectedDay?.schedule_id === day.schedule_id ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-blue-300'" class="rounded-lg border px-4 py-2 text-sm font-bold" x-text="day.label"></button></template>
                    </div>
                </div>

                <div class="mt-5" x-show="selectedDay">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500">Actual date · <span x-text="monthLabel"></span></p>
                    <div class="mt-2 flex gap-2 overflow-x-auto pb-1">
                        <template x-for="date in availableDates" :key="date.value"><button type="button" @click="selectDate(date.value)" :class="selectedDate === date.value ? 'border-[#10294b] bg-[#10294b] text-white' : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-blue-300'" class="min-w-[74px] rounded-lg border px-3 py-2 text-center"><span class="block text-[10px] font-bold uppercase" x-text="date.shortDay"></span><span class="mt-0.5 block text-base font-extrabold" x-text="date.dayNumber"></span></button></template>
                    </div>
                </div>
            </div>

            <div class="p-5 sm:p-6">
                <div x-show="loading" class="grid min-h-52 place-items-center"><div class="text-center"><div class="mx-auto h-9 w-9 animate-spin rounded-full border-4 border-slate-200 border-t-blue-600"></div><p class="mt-3 text-sm font-semibold text-slate-500">Loading class attendance…</p></div></div>
                <div x-show="error && !loading" class="rounded-xl border border-red-200 bg-red-50 p-5 text-sm font-semibold text-red-700" x-text="error"></div>

                <div x-show="attendance && !loading && !error" x-cloak>
                    <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                        <div><p class="text-xs font-extrabold uppercase tracking-wider text-blue-600">Student attendance</p><h3 class="mt-1 text-2xl font-extrabold text-slate-950" x-text="attendance?.class.date_label"></h3><p class="mt-1 text-sm text-slate-500" x-text="attendance ? attendance.class.time_label + ' · Room ' + attendance.class.room : ''"></p></div>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                            <div class="rounded-xl bg-emerald-50 px-4 py-3 text-center"><p class="text-[10px] font-bold uppercase text-emerald-700">Present</p><p class="mt-1 text-2xl font-extrabold text-emerald-700" x-text="attendance?.summary.present"></p></div>
                            <div class="rounded-xl bg-rose-50 px-4 py-3 text-center"><p class="text-[10px] font-bold uppercase text-rose-700">Absent</p><p class="mt-1 text-2xl font-extrabold text-rose-700" x-text="attendance?.summary.absent"></p></div>
                            <div class="rounded-xl bg-violet-50 px-4 py-3 text-center"><p class="text-[10px] font-bold uppercase text-violet-700">Excused</p><p class="mt-1 text-2xl font-extrabold text-violet-700" x-text="attendance?.summary.excused"></p></div>
                            <div class="rounded-xl bg-amber-50 px-4 py-3 text-center"><p class="text-[10px] font-bold uppercase text-amber-700">Pending</p><p class="mt-1 text-2xl font-extrabold text-amber-700" x-text="attendance?.summary.pending"></p></div>
                        </div>
                    </div>

                    <div x-show="attendance && !attendance.has_students" class="mt-6 rounded-xl bg-slate-50 p-8 text-center"><p class="font-semibold text-slate-700">No students are enrolled in this section.</p></div>
                    <div x-show="attendance?.has_students && !attendance?.has_records" class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">No attendance recorded for this class yet. Students remain Pending until the attendance cutoff, then appear as Absent.</div>

                    <div x-show="attendance?.has_students" class="mt-6 overflow-x-auto rounded-xl border border-slate-200">
                        <table class="min-w-full text-sm"><thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Student ID</th><th class="px-5 py-3">Student Name</th><th class="px-5 py-3">Time In</th><th class="px-5 py-3">Status</th></tr></thead>
                        <tbody class="divide-y divide-slate-100"><template x-for="student in attendance?.students || []" :key="student.student_no"><tr><td class="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-500" x-text="student.student_no"></td><td class="px-5 py-4 font-semibold text-slate-900" x-text="student.name"></td><td class="whitespace-nowrap px-5 py-4 tabular-nums text-slate-600" x-text="student.time_in || '—'"></td><td class="px-5 py-4"><span :class="statusClass(student.status)" class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-bold uppercase" x-text="student.status"></span></td></tr></template></tbody></table>
                    </div>
                </div>
            </div>
        </section>
    @endif
</div>

@if($scheduleGroups->isNotEmpty())
<script>
function classAttendanceBrowser() {
    return {
        selectedGroup: null, selectedDay: null, selectedDate: '', availableDates: [], attendance: null, loading: false, error: '',
        today: @js(now()->toDateString()),
        endpoint: @js(url('/instructor/schedule/__SCHEDULE__/attendance')),
        get monthLabel() { return new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(this.today + 'T00:00:00Z')); },
        openGroup(group) {
            this.selectedGroup = group; this.attendance = null; this.error = '';
            const todayName = new Intl.DateTimeFormat('en-US', { weekday: 'long', timeZone: 'UTC' }).format(new Date(this.today + 'T00:00:00Z')).toLowerCase();
            this.selectDay(group.days.find(day => day.name === todayName) || group.days[0]);
            this.$nextTick(() => this.$root.querySelector('section[x-show="selectedGroup"]')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
        },
        selectDay(day) {
            this.selectedDay = day; this.attendance = null; this.error = '';
            const base = new Date(this.today + 'T00:00:00Z'); const year = base.getUTCFullYear(); const month = base.getUTCMonth();
            this.availableDates = [];
            for (let number = 1; number <= new Date(Date.UTC(year, month + 1, 0)).getUTCDate(); number++) {
                const candidate = new Date(Date.UTC(year, month, number));
                const name = new Intl.DateTimeFormat('en-US', { weekday: 'long', timeZone: 'UTC' }).format(candidate).toLowerCase();
                if (name === day.name) this.availableDates.push({ value: candidate.toISOString().slice(0, 10), shortDay: day.label.slice(0, 3), dayNumber: number });
            }
            const defaultDate = [...this.availableDates].reverse().find(date => date.value <= this.today) || this.availableDates[0];
            if (defaultDate) this.selectDate(defaultDate.value);
        },
        async selectDate(date) {
            this.selectedDate = date; this.loading = true; this.error = ''; this.attendance = null;
            try {
                const url = this.endpoint.replace('__SCHEDULE__', this.selectedDay.schedule_id) + '?date=' + encodeURIComponent(date);
                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Attendance could not be loaded.');
                this.attendance = data;
            } catch (error) { this.error = error.message || 'Attendance could not be loaded.'; }
            finally { this.loading = false; }
        },
        statusClass(status) {
            return { present: 'bg-emerald-100 text-emerald-700', late: 'bg-orange-100 text-orange-700', absent: 'bg-rose-100 text-rose-700', excused: 'bg-violet-100 text-violet-700', pending: 'bg-amber-100 text-amber-700' }[status] || 'bg-slate-100 text-slate-600';
        },
    };
}
</script>
@endif
</x-instructor-layout>
