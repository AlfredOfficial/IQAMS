<x-scanner-layout>
<main class="min-h-screen bg-[#f4f7fa] text-slate-900" x-data="scannerApp()" x-init="focus()" @click="focus()">
    @unless($terminal)
        <div class="grid min-h-screen place-items-center p-6"><section class="w-full max-w-2xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
            <div class="flex items-center gap-4 border-b border-slate-200 pb-6"><img src="{{ asset('favicon.svg') }}" alt="IQAMS" class="h-14 w-14"><div><h1 class="text-2xl font-bold text-[#123f70]">IQAMS Attendance Terminal</h1><p class="text-slate-500">Continuous QR attendance kiosk</p></div></div>
            <h2 class="mt-6 text-lg font-semibold">Select this computer's registered terminal</h2>
            <form method="POST" action="{{ route('attendance-scanner.terminal') }}" class="mt-4 flex gap-3">@csrf
                <select name="scanner_terminal_id" required class="min-w-0 flex-1 rounded-lg border-slate-300 bg-white"><option value="">Choose a terminal</option>@foreach($terminals as $item)<option value="{{ $item->id }}">{{ $item->name }} — {{ $item->location }}</option>@endforeach</select>
                <button class="rounded-lg bg-[#124f87] px-5 py-2 font-semibold text-white">Use terminal</button>
            </form>
            @if($terminals->isEmpty())<p class="mt-4 text-amber-700">No active terminal exists. Register one in Scanner Security.</p>@endif
        </section></div>
    @else
        <input x-ref="qr" x-model="qr" @input.debounce.350ms="scan()" @keydown.enter.prevent="scan()" class="fixed left-[-9999px]" autocomplete="off" aria-label="QR scanner input">

        <section x-show="state === 'ready'" class="grid min-h-screen place-items-center p-8 text-center"><div>
            <img src="{{ asset('favicon.svg') }}" alt="IQAMS" class="mx-auto h-24 w-24"><p class="mt-7 text-sm font-bold uppercase tracking-[.24em] text-[#1b5b91]">IQAMS Attendance Terminal</p>
            <h1 class="mt-3 text-4xl font-bold">Waiting for QR card</h1><p class="mt-3 text-lg text-slate-500">Scan a school ID to record attendance.</p><p class="mt-10 text-sm text-slate-400">{{ $terminal->name }} · {{ $terminal->location }}</p>
        </div></section>

        <section x-show="state === 'processing'" x-cloak class="grid min-h-screen place-items-center text-center"><div><div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-slate-200 border-t-[#1b5b91]"></div><p class="mt-5 text-xl font-medium">Validating attendance…</p></div></section>

        <section x-show="state === 'result'" x-cloak class="min-h-screen bg-white">
            <template x-if="result.person"><div class="grid min-h-screen grid-cols-1 lg:grid-cols-12">
                <div class="col-span-1 flex min-h-0 flex-col bg-[#eef3f7] p-3 sm:p-5 lg:col-span-7 lg:min-h-screen lg:p-8">
                    <header class="mb-4 flex flex-wrap items-center justify-between gap-3 sm:mb-5"><div class="flex min-w-0 items-center gap-2 sm:gap-3"><img src="{{ asset('favicon.svg') }}" alt="IQAMS" class="h-9 w-9 shrink-0 sm:h-12 sm:w-12"><div class="min-w-0"><p class="text-lg font-extrabold tracking-[.12em] text-[#123f70] sm:text-xl">IQAMS</p><p class="truncate text-[10px] text-slate-500 sm:text-xs">Integrated QR Attendance Monitoring System</p></div></div><p class="text-right text-[10px] font-medium text-slate-500 sm:text-xs">{{ $terminal->name }}<br>{{ $terminal->location }}</p></header>
                    <div class="h-[clamp(13rem,36vh,24rem)] overflow-hidden rounded-xl bg-slate-200 shadow-sm sm:h-[clamp(16rem,42vh,30rem)] lg:h-[clamp(20rem,55vh,36rem)]"><img x-show="result.person.avatar" :src="result.person.avatar" class="h-full w-full object-cover object-center" :alt="result.person.name"><div x-show="!result.person.avatar" class="grid h-full place-items-center text-6xl font-bold text-slate-400 sm:text-8xl lg:text-9xl" x-text="result.person.initials"></div></div>
                </div>

                <div class="col-span-1 flex min-h-0 flex-col px-4 py-5 sm:px-8 sm:py-7 lg:col-span-5 lg:min-h-screen lg:px-12 lg:py-10">
                    <div class="flex items-center justify-between gap-3 border-b border-slate-200 pb-4 sm:pb-5"><p class="text-[10px] font-bold uppercase tracking-[.2em] text-slate-400 sm:text-xs">Verified identity</p><span class="shrink-0 rounded-md bg-[#e8f0f7] px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-[#124f87] sm:px-4 sm:py-2 sm:text-sm" x-text="result.person.role"></span></div>
                    <div class="flex flex-col justify-center py-6 lg:flex-1">
                        <h1 class="break-words text-2xl font-extrabold leading-tight text-[#102f4f] sm:text-4xl lg:text-5xl" x-text="result.person.name"></h1><p class="mt-2 text-lg font-medium text-slate-500 sm:mt-3 sm:text-xl" x-text="result.person.role"></p>
                        <dl class="mt-7 space-y-4 border-y border-slate-200 py-5 sm:mt-9 sm:py-6"><template x-for="detail in result.person.details" :key="detail.label"><div class="grid grid-cols-1 gap-1 sm:grid-cols-[8.5rem_minmax(0,1fr)] sm:items-baseline sm:gap-4"><dt class="text-xs font-bold uppercase tracking-wide text-[#1b5b91] sm:text-sm" x-text="detail.label"></dt><dd class="break-words text-base font-semibold leading-snug text-slate-800 sm:text-lg" x-text="detail.value"></dd></div></template></dl>
                        <div class="mt-6 border-l-4 pl-4 sm:mt-8 sm:pl-5" :class="result.code === 'recorded' ? 'border-emerald-600' : (result.code === 'already_recorded' ? 'border-amber-500' : 'border-red-600')"><p class="text-xs font-bold uppercase tracking-[.18em] sm:text-sm" :class="resultClass" x-text="result.code === 'recorded' ? 'Scan Successful' : result.title"></p><p class="mt-1 text-xl font-bold sm:text-2xl" x-text="result.title"></p><p class="mt-2 text-sm leading-relaxed text-slate-500" x-text="result.message"></p></div>
                        <template x-if="result.attendance"><div class="mt-6 flex flex-wrap items-end justify-between gap-4 sm:mt-7"><div><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 sm:text-xs">Attendance status</p><p class="mt-1 text-xl font-extrabold uppercase text-[#123f70] sm:text-2xl" x-text="statusLabel"></p></div><div class="text-left sm:text-right"><p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 sm:text-xs" x-text="attendanceLabel"></p><p class="mt-1 text-2xl font-extrabold tabular-nums sm:text-3xl" x-text="result.attendance.display_time || result.attendance.recorded_time"></p></div></div></template>
                    </div>
                    <p class="border-t border-slate-200 pt-4 text-center text-xs text-slate-400">Ready for the next scan in a moment</p>
                </div>
            </div></template>
            <template x-if="!result.person"><div class="grid min-h-screen place-items-center p-8 text-center"><div class="max-w-xl"><img src="{{ asset('favicon.svg') }}" alt="IQAMS" class="mx-auto h-20 w-20"><p class="mt-7 text-sm font-bold uppercase tracking-[.2em]" :class="resultClass" x-text="result.title"></p><h1 class="mt-3 text-3xl font-bold">Scan could not be completed</h1><p class="mt-4 text-lg text-slate-500" x-text="result.message"></p></div></div></template>
        </section>
    @endunless
</main>

@if($terminal)
<script>
function scannerApp() {
    return {
        qr: '', state: 'ready', busy: false, resetTimer: null,
        result: { code: '', title: '', message: '', person: null, attendance: null },
        get resultClass() { if (this.result.code === 'recorded') return 'text-emerald-700'; if (this.result.code === 'already_recorded') return 'text-amber-700'; return 'text-red-700'; },
        get attendanceLabel() { const value = this.result.attendance?.period || this.result.attendance?.type || ''; return value.replaceAll('_', ' '); },
        get statusLabel() { const status = this.result.attendance?.status || ''; return status === 'present' ? 'On Time' : status.replaceAll('_', ' '); },
        focus() { this.$nextTick(() => this.$refs.qr?.focus()); },
        async scan() {
            const value = this.qr.trim(); this.qr = '';
            if (!value || this.busy || this.state !== 'ready') return;
            this.busy = true; this.state = 'processing';
            try {
                const response = await fetch(@js(route('attendance-scanner.scan')), { method: 'POST', headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ qr_code: value }) });
                const data = await response.json().catch(() => ({}));
                this.showResult(data.code ? data : { code: 'rejected', title: 'Attendance Not Recorded', message: data.message || 'The scanner request failed.', person: null, attendance: null });
            } catch (error) { this.showResult({ code: 'rejected', title: 'Scanner Unavailable', message: 'Unable to reach the attendance server. Please try again.', person: null, attendance: null }); }
        },
        showResult(result) { this.result = result; this.state = 'result'; clearTimeout(this.resetTimer); this.resetTimer = setTimeout(() => this.reset(), 800); },
        reset() { clearTimeout(this.resetTimer); this.qr = ''; this.result = { code: '', title: '', message: '', person: null, attendance: null }; this.busy = false; this.state = 'ready'; this.focus(); },
    };
}
</script>
@endif
</x-scanner-layout>
