<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold leading-tight text-gray-800">QR Attendance Scanner</h2></x-slot>

    <div class="py-8" x-data="attendanceScanner()" x-init="initialize()">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-slate-950 text-white shadow-xl ring-1 ring-slate-800">
                <div class="grid gap-8 p-6 md:grid-cols-[1fr_auto] md:items-center md:p-10">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="relative flex h-3 w-3"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex h-3 w-3 rounded-full bg-emerald-500"></span></span>
                            <p class="text-sm font-bold tracking-[0.22em] text-emerald-400" x-text="processing ? 'PROCESSING SCAN' : 'READY TO SCAN'"></p>
                        </div>
                        <h3 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">Place the user's QR code in front of the scanner.</h3>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">The T-D4 sends the QR value as USB keyboard input. Attendance is submitted automatically when the scanner finishes typing.</p>
                    </div>
                    <div class="rounded-xl bg-white/10 px-5 py-4 text-center ring-1 ring-white/15"><p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Queued</p><p class="mt-1 text-3xl font-bold tabular-nums" x-text="pendingScans.length"></p></div>
                </div>

                <form @submit.prevent="queueScan" class="border-t border-white/10 bg-white/5 p-6 md:px-10">
                    <label for="qr-code" class="block text-sm font-semibold text-white">Dedicated scanner input</label>
                    <input id="qr-code" x-ref="qrInput" x-model="qrCode" type="text" autocomplete="off" autocapitalize="off" spellcheck="false"
                           @input="markInput" @keydown="trackKey($event)" @blur="restoreFocus"
                           class="mt-2 w-full rounded-xl border-2 border-emerald-400 bg-white px-5 py-4 font-mono text-xl font-semibold tracking-wide text-slate-900 shadow-sm focus:border-emerald-400 focus:ring-4 focus:ring-emerald-400/25"
                           placeholder="Waiting for USB HID scanner input…">
                    <p class="mt-2 text-xs text-slate-400">No Scan button is needed. Enter/CR is supported but not required.</p>
                </form>
            </section>

            <div class="grid gap-6 lg:grid-cols-5">
                <section class="rounded-2xl p-6 shadow-sm ring-1 lg:col-span-2" :class="result.kind === 'success' ? 'bg-emerald-50 ring-emerald-200' : (result.kind === 'error' ? 'bg-red-50 ring-red-200' : 'bg-white ring-gray-200')">
                    <p class="text-xs font-bold uppercase tracking-[0.18em]" :class="result.kind === 'success' ? 'text-emerald-700' : (result.kind === 'error' ? 'text-red-700' : 'text-gray-500')" x-text="result.kind === 'success' ? 'Attendance accepted' : (result.kind === 'error' ? 'Scan rejected' : 'Scanner ready')"></p>
                    <h3 class="mt-2 text-xl font-semibold text-gray-900" x-text="result.message"></h3>
                    <template x-if="result.attendance">
                        <div class="mt-6">
                            <div class="flex items-center gap-4">
                                <div class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-2xl bg-white text-xl font-bold text-indigo-700 ring-1 ring-gray-200"><img x-show="result.attendance.avatar" :src="result.attendance.avatar" alt="" class="h-full w-full object-cover"><span x-show="!result.attendance.avatar" x-text="result.attendance.initials"></span></div>
                                <div class="min-w-0"><p class="truncate text-xl font-bold text-gray-900" x-text="result.attendance.name"></p><p class="mt-1 font-mono text-sm text-gray-600" x-text="result.attendance.identifier"></p></div>
                            </div>
                            <dl class="mt-6 space-y-3 text-sm">
                                <div class="flex justify-between gap-4"><dt class="text-gray-500">User ID</dt><dd class="text-right font-semibold text-gray-900" x-text="result.attendance.user_id"></dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-gray-500">Role</dt><dd class="text-right font-semibold text-gray-900" x-text="result.attendance.role"></dd></div>
                                <div x-show="result.attendance.department" class="flex justify-between gap-4"><dt class="text-gray-500">Department</dt><dd class="text-right font-semibold text-gray-900" x-text="result.attendance.department"></dd></div>
                                <div x-show="result.attendance.course_section" class="flex justify-between gap-4"><dt class="text-gray-500">Course / Section</dt><dd class="text-right font-semibold text-gray-900" x-text="result.attendance.course_section"></dd></div>
                                <div x-show="result.attendance.subject" class="flex justify-between gap-4"><dt class="text-gray-500">Subject</dt><dd class="text-right font-semibold text-gray-900" x-text="`${result.attendance.subject_code || ''} ${result.attendance.subject || ''}`.trim()"></dd></div>
                                <div x-show="result.attendance.schedule" class="flex justify-between gap-4"><dt class="text-gray-500">Schedule</dt><dd class="text-right font-semibold text-gray-900" x-text="result.attendance.schedule"></dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-gray-500">Attendance</dt><dd class="text-right font-semibold capitalize text-gray-900" x-text="result.attendance.attendance_type_label"></dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-gray-500">Status</dt><dd class="text-right font-bold text-gray-900" x-text="result.attendance.status_label"></dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-gray-500">Scan time</dt><dd class="text-right font-semibold text-gray-900" x-text="result.attendance.scan_time"></dd></div>
                            </dl>
                        </div>
                    </template>
                </section>

                <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200 lg:col-span-3">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-100 px-6 py-5">
                        <div><h3 class="font-semibold text-gray-900">Recent attendance</h3><p class="mt-1 text-sm text-gray-500">Updates immediately after every accepted scan.</p></div>
                        <div><label for="scanner-location" class="block text-xs font-semibold uppercase tracking-wider text-gray-500">Scanner location</label><input id="scanner-location" x-model="location" @change="saveLocation" @keydown.enter.prevent="saveLocation(); focusInput()" class="mt-1 w-52 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Main scanner"></div>
                    </div>
                    <div class="max-h-[32rem] overflow-auto">
                        <template x-if="!recentAttendance.length"><p class="px-6 py-14 text-center text-sm text-gray-400">No attendance scans yet.</p></template>
                        <div class="divide-y divide-gray-100">
                            <template x-for="scan in recentAttendance" :key="scan.id">
                                <div class="flex items-center gap-4 px-6 py-4">
                                    <div class="grid h-11 w-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-indigo-50 font-bold text-indigo-700"><img x-show="scan.avatar" :src="scan.avatar" alt="" class="h-full w-full object-cover"><span x-show="!scan.avatar" x-text="scan.initials"></span></div>
                                    <div class="min-w-0 flex-1"><div class="flex items-center justify-between gap-3"><p class="truncate font-semibold text-gray-900" x-text="scan.name"></p><p class="shrink-0 text-xs tabular-nums text-gray-500" x-text="scan.scan_time"></p></div><p class="mt-1 truncate text-sm text-gray-500" x-text="scan.subject ? `${scan.subject_code || ''} ${scan.subject}`.trim() : `${scan.role} · ${scan.attendance_type_label}`"></p></div>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-bold capitalize" :class="scan.status === 'late' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'" x-text="scan.status_label"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script>
        function attendanceScanner() {
            return {
                qrCode: '', location: localStorage.getItem('iqamsScannerLocation') || '', processing: false,
                pendingScans: [], idleTimer: null,
                idleSubmitMilliseconds: @js(config('attendance.scanner_idle_submit_milliseconds')),
                recentAttendance: @js($recentAttendance),
                result: { kind: 'idle', message: 'READY TO SCAN', attendance: null },

                initialize() {
                    this.focusInput();
                    window.addEventListener('focus', () => this.focusInput());
                    document.addEventListener('visibilitychange', () => { if (!document.hidden) this.focusInput(); });
                },
                focusInput() { this.$nextTick(() => this.$refs.qrInput?.focus({ preventScroll: true })); },
                restoreFocus(event) { if (event.relatedTarget?.id !== 'scanner-location') setTimeout(() => this.focusInput(), 0); },
                markInput() {
                    clearTimeout(this.idleTimer);
                    if (!this.qrCode.trim()) return;
                    this.idleTimer = setTimeout(() => this.queueScan(), this.idleSubmitMilliseconds);
                },
                trackKey(event) { if (event.key === 'Enter') clearTimeout(this.idleTimer); },
                queueScan() {
                    clearTimeout(this.idleTimer);
                    const value = String(this.qrCode || '').trim();
                    this.resetInput();
                    if (!value) return this.showError('The scanner did not send a QR value.');
                    if (value.length < 2) return this.showError('The scanned QR value is too short.');
                    this.pendingScans.push(value);
                    this.processNextScan();
                },
                resetInput() { this.qrCode = ''; this.idleTimer = null; },
                showError(message) { this.result = { kind: 'error', message, attendance: null }; this.focusInput(); },
                saveLocation() { localStorage.setItem('iqamsScannerLocation', this.location); },
                async processNextScan() {
                    if (this.processing || !this.pendingScans.length) return;
                    this.processing = true;
                    const value = this.pendingScans.shift();
                    this.saveLocation();
                    try {
                        const response = await fetch(@js(route('attendance-scanner.store')), {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: JSON.stringify({ qr_code: value, scanner_location: this.location || null }),
                        });
                        const data = await response.json().catch(() => ({}));
                        const validationMessage = Object.values(data.errors || {}).flat()[0];
                        if (!response.ok) throw new Error(validationMessage || data.message || 'Attendance could not be recorded.');
                        this.result = { kind: 'success', message: data.message, attendance: data.attendance };
                        this.recentAttendance = data.recent_attendance || [data.attendance, ...this.recentAttendance].slice(0, 10);
                    } catch (error) {
                        const message = error instanceof TypeError ? 'The scanner could not reach the server. Check the network connection and scan again.' : error.message;
                        this.result = { kind: 'error', message, attendance: null };
                    } finally {
                        this.processing = false;
                        this.focusInput();
                        if (this.pendingScans.length) setTimeout(() => this.processNextScan(), 50);
                    }
                },
            };
        }
    </script>
</x-app-layout>
