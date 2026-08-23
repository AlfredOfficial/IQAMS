<x-scanner-layout>
    <main class="relative h-screen overflow-hidden bg-slate-950 text-white" x-data="attendanceScanner()" x-init="initialize()" @click="focusInput()">
        <div class="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
            <div class="absolute left-1/2 top-[-18rem] h-[38rem] w-[38rem] -translate-x-1/2 rounded-full bg-blue-600/10 blur-3xl"></div>
            <div class="absolute bottom-[-16rem] right-[-10rem] h-[34rem] w-[34rem] rounded-full bg-emerald-500/5 blur-3xl"></div>
        </div>

        <div class="relative flex h-screen flex-col px-4 py-4 sm:px-6 lg:px-10">
            <header class="flex min-h-12 items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="grid h-9 w-9 place-items-center rounded-lg border border-white/10 bg-white/5 text-sm font-bold">IQ</div>
                    <div class="hidden sm:block">
                        <p class="text-sm font-semibold">IQAMS Attendance Terminal</p>
                        <p class="text-xs text-slate-500">Physical QR scanner</p>
                    </div>
                </div>
                <div class="flex items-center gap-2" @click.stop>
                    <label for="scanner-location" class="hidden text-xs font-medium text-slate-500 sm:block">Terminal location</label>
                    <input id="scanner-location" x-model="location" @change="saveLocation" @keydown.enter.prevent="saveLocation(); focusInput()" @blur="saveLocation(); restoreFocus()"
                           class="w-36 rounded-lg border-white/10 bg-white/5 px-3 py-2 text-right text-xs text-slate-300 placeholder:text-slate-600 focus:border-blue-500/60 focus:ring-blue-500/20 sm:w-48"
                           placeholder="Main scanner" aria-label="Scanner location">
                </div>
            </header>

            <input x-ref="qrInput" x-model="qrCode" type="text" inputmode="none" autocomplete="off" autocapitalize="off" spellcheck="false" tabindex="-1"
                   aria-label="Physical QR scanner input" @input="markInput" @keydown.enter.prevent="queueScan()" @blur="restoreFocus($event)"
                   class="fixed left-[-9999px] top-0 h-px w-px opacity-0">

            <section class="flex min-h-0 flex-1 items-center justify-center py-3 sm:py-4">
                <div class="h-full w-full">
                    <div class="h-full" x-show="state === 'ready' || state === 'processing'" x-transition.opacity.duration.250ms>
                        <div class="flex h-full w-full items-center">
                            <div class="relative h-full w-full overflow-hidden rounded-[1.75rem] border border-white/10 bg-slate-900/60 shadow-2xl shadow-black/30 sm:rounded-[2rem]">
                                <div class="absolute left-5 top-5 h-16 w-16 rounded-tl-xl border-l-2 border-t-2 border-blue-400/80 sm:left-8 sm:top-8 sm:h-24 sm:w-24"></div>
                                <div class="absolute right-5 top-5 h-16 w-16 rounded-tr-xl border-r-2 border-t-2 border-blue-400/80 sm:right-8 sm:top-8 sm:h-24 sm:w-24"></div>
                                <div class="absolute bottom-5 left-5 h-16 w-16 rounded-bl-xl border-b-2 border-l-2 border-blue-400/80 sm:bottom-8 sm:left-8 sm:h-24 sm:w-24"></div>
                                <div class="absolute bottom-5 right-5 h-16 w-16 rounded-br-xl border-b-2 border-r-2 border-blue-400/80 sm:bottom-8 sm:right-8 sm:h-24 sm:w-24"></div>
                                <div class="absolute inset-0 grid place-items-center">
                                    <div class="text-center">
                                        <svg class="mx-auto h-20 w-20 text-slate-600 sm:h-28 sm:w-28" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.25" d="M4 4h6v6H4V4Zm10 0h6v6h-6V4ZM4 14h6v6H4v-6Zm10 0h2m4 0v2m-6 2h2v2m2-4h2v4h-4v-2"/></svg>
                                        <p class="mt-5 text-base font-medium text-slate-400 sm:text-lg" x-text="state === 'processing' ? 'QR detected' : 'Waiting for QR input'"></p>
                                    </div>
                                </div>
                                <div x-show="state === 'ready'" class="scanner-line absolute left-8 right-8 h-px bg-gradient-to-r from-transparent via-blue-400 to-transparent shadow-[0_0_14px_rgba(96,165,250,0.8)] sm:left-12 sm:right-12"></div>
                                <div x-show="state === 'processing'" class="absolute inset-0 grid place-items-center bg-slate-950/55 backdrop-blur-sm">
                                    <svg class="h-9 w-9 animate-spin text-blue-400" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle class="opacity-20" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="state === 'success'" x-cloak x-transition.opacity.duration.250ms>
                        <div class="mx-auto grid max-w-5xl items-center gap-7 lg:grid-cols-[minmax(0,0.85fr)_minmax(0,1.15fr)] lg:gap-12">
                            <div class="text-center lg:text-right">
                                <div class="relative mx-auto h-44 w-44 overflow-hidden rounded-[2rem] border-4 border-emerald-400/40 bg-slate-800 shadow-2xl shadow-emerald-950/40 sm:h-52 sm:w-52 lg:ml-auto lg:mr-0 lg:h-60 lg:w-60">
                                    <img x-show="result.attendance && result.attendance.avatar" :src="result.attendance && result.attendance.avatar" :alt="result.attendance ? result.attendance.name : ''" class="h-full w-full object-cover">
                                    <div x-show="result.attendance && !result.attendance.avatar" class="grid h-full w-full place-items-center bg-gradient-to-br from-slate-700 to-slate-900 text-5xl font-bold text-slate-300" x-text="result.attendance && result.attendance.initials"></div>
                                </div>
                            </div>
                            <div class="min-w-0 text-center lg:text-left">
                                <div class="inline-flex items-center gap-2 rounded-full bg-emerald-400/10 px-3 py-1.5 text-xs font-bold uppercase tracking-[0.16em] text-emerald-300 ring-1 ring-inset ring-emerald-400/25">
                                    <x-heroicon-m-check class="h-4 w-4" aria-hidden="true" />
                                    Attendance recorded
                                </div>
                                <h1 class="mt-4 truncate text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl" x-text="result.attendance && result.attendance.name"></h1>
                                <p class="mt-2 font-mono text-sm tracking-wide text-slate-400" x-text="result.attendance && result.attendance.identifier"></p>
                                <div class="mt-5 flex flex-wrap justify-center gap-2 lg:justify-start">
                                    <span class="rounded-full bg-white/5 px-3 py-1 text-xs font-semibold text-slate-300 ring-1 ring-inset ring-white/10" x-text="result.attendance && result.attendance.role"></span>
                                    <span x-show="result.attendance && result.attendance.department" class="rounded-full bg-white/5 px-3 py-1 text-xs font-semibold text-slate-300 ring-1 ring-inset ring-white/10" x-text="result.attendance && result.attendance.department"></span>
                                    <span x-show="result.attendance && result.attendance.course_section" class="rounded-full bg-white/5 px-3 py-1 text-xs font-semibold text-slate-300 ring-1 ring-inset ring-white/10" x-text="result.attendance && result.attendance.course_section"></span>
                                </div>
                                <div x-show="result.attendance && (result.attendance.subject || result.attendance.schedule || result.attendance.event)" class="mt-6 rounded-2xl border border-white/10 bg-white/[0.035] p-4 sm:p-5">
                                    <p x-show="result.attendance && result.attendance.event" class="text-base font-semibold" x-text="result.attendance && result.attendance.event"></p>
                                    <p x-show="result.attendance && result.attendance.subject" class="text-base font-semibold" x-text="result.attendance ? `${result.attendance.subject_code || ''} ${result.attendance.subject || ''}`.trim() : ''"></p>
                                    <p x-show="result.attendance && result.attendance.schedule" class="mt-1.5 text-sm text-slate-400" x-text="result.attendance && result.attendance.schedule"></p>
                                </div>
                                <div class="mt-6 flex flex-col items-center gap-4 sm:flex-row sm:justify-center lg:justify-start">
                                    <div class="rounded-2xl px-5 py-3 text-center ring-1 ring-inset" :class="result.attendance && result.attendance.status === 'late' ? 'bg-amber-400/10 text-amber-300 ring-amber-400/25' : 'bg-emerald-400/10 text-emerald-300 ring-emerald-400/25'">
                                        <p class="text-2xl font-bold uppercase tracking-wide" x-text="result.attendance && result.attendance.status_label"></p>
                                        <p class="mt-0.5 text-xs font-medium opacity-80" x-text="result.attendance && result.attendance.attendance_type_label"></p>
                                    </div>
                                    <div class="text-center sm:text-left">
                                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Scan time</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-200" x-text="result.attendance && result.attendance.scan_time"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="state === 'error'" x-cloak x-transition.opacity.duration.250ms>
                        <div class="mx-auto max-w-xl text-center">
                            <div class="mx-auto grid h-24 w-24 place-items-center rounded-full bg-red-400/10 text-red-300 ring-1 ring-inset ring-red-400/25">
                                <x-heroicon-o-x-mark class="h-11 w-11" aria-hidden="true" />
                            </div>
                            <p class="mt-6 text-xs font-bold uppercase tracking-[0.18em] text-red-300">Attendance not recorded</p>
                            <h1 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Scan rejected</h1>
                            <p class="mx-auto mt-4 max-w-lg text-base leading-7 text-slate-300" x-text="result.message"></p>
                            <p class="mt-7 text-xs text-slate-600">The scanner will reset automatically.</p>
                        </div>
                    </div>
                </div>
            </section>

            <footer class="flex min-h-8 items-center justify-center text-center text-[11px] text-slate-700">
                <span x-show="pendingScans.length" x-text="`${pendingScans.length} scan${pendingScans.length === 1 ? '' : 's'} queued`"></span>
            </footer>
        </div>
    </main>

    <style>
        [x-cloak] { display: none !important; }
        @keyframes scanner-sweep { 0%, 100% { top: 2rem; opacity: .35; } 50% { top: calc(100% - 2rem); opacity: 1; } }
        .scanner-line { animation: scanner-sweep 3.2s ease-in-out infinite; }
        @media (prefers-reduced-motion: reduce) { .scanner-line { animation: none; top: 50%; } }
    </style>

    <script>
        function attendanceScanner() {
            return {
                qrCode: '', location: localStorage.getItem('iqamsScannerLocation') || '', state: 'ready', processing: false,
                pendingScans: [], idleTimer: null, resetTimer: null,
                idleSubmitMilliseconds: @js(config('attendance.scanner_idle_submit_milliseconds')),
                result: { message: '', attendance: null },
                initialize() {
                    this.focusInput();
                    window.addEventListener('focus', () => this.focusInput());
                    document.addEventListener('visibilitychange', () => { if (!document.hidden) this.focusInput(); });
                },
                focusInput() { this.$nextTick(() => this.$refs.qrInput?.focus({ preventScroll: true })); },
                restoreFocus(event = null) {
                    if (event?.relatedTarget?.id === 'scanner-location') return;
                    setTimeout(() => this.focusInput(), 0);
                },
                markInput() {
                    clearTimeout(this.idleTimer);
                    if (!this.qrCode.trim()) return;
                    this.idleTimer = setTimeout(() => this.queueScan(), this.idleSubmitMilliseconds);
                },
                queueScan() {
                    clearTimeout(this.idleTimer);
                    const value = String(this.qrCode || '').trim();
                    this.qrCode = ''; this.idleTimer = null;
                    if (!value) return;
                    if (value.length < 2) return this.showError('The scanned QR value is too short.');
                    clearTimeout(this.resetTimer);
                    this.pendingScans.push(value);
                    this.processNextScan();
                },
                showError(message) {
                    this.result = { message, attendance: null };
                    this.state = 'error';
                    this.scheduleReset(1000);
                    this.focusInput();
                },
                saveLocation() { localStorage.setItem('iqamsScannerLocation', this.location); },
                scheduleReset(delay) {
                    clearTimeout(this.resetTimer);
                    this.resetTimer = setTimeout(() => {
                        if (this.processing || this.pendingScans.length) return;
                        this.result = { message: '', attendance: null };
                        this.state = 'ready';
                        this.focusInput();
                    }, delay);
                },
                async processNextScan() {
                    if (this.processing || !this.pendingScans.length) return;
                    clearTimeout(this.resetTimer);
                    this.processing = true; this.state = 'processing'; this.result = { message: '', attendance: null };
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
                        this.result = { message: data.message, attendance: data.attendance };
                        this.state = 'success';
                        this.scheduleReset(1000);
                    } catch (error) {
                        const message = error instanceof TypeError ? 'The scanner could not reach the server. Check the network connection and scan again.' : error.message;
                        this.result = { message, attendance: null };
                        this.state = 'error';
                        this.scheduleReset(1000);
                    } finally {
                        this.processing = false;
                        this.focusInput();
                        if (this.pendingScans.length) setTimeout(() => this.processNextScan(), 50);
                    }
                },
            };
        }
    </script>
</x-scanner-layout>
