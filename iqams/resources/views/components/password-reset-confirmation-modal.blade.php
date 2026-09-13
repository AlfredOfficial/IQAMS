<div x-data="{
        show: false,
        form: null,
        open(form) { this.form = form; this.show = true; },
        close() { this.show = false; this.form = null; },
        continueToPasswordConfirmation() {
            const form = this.form;
            this.close();
            if (form) window.dispatchEvent(new CustomEvent('password-confirmation-required', { detail: { form } }));
        }
    }"
    @password-reset-confirmation-required.window="open($event.detail.form)"
    @keydown.escape.window="if (show) close()">
    <div x-show="show" x-cloak x-transition.opacity class="fixed inset-0 z-[115] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="password-reset-confirmation-title">
        <div @click.outside="close()" class="w-full max-w-md rounded-xl bg-white p-6 shadow-2xl">
            <h2 id="password-reset-confirmation-title" class="text-lg font-semibold text-gray-900">Send password reset link?</h2>
            <p class="mt-2 text-sm text-gray-600">The current password and sessions will stop working immediately.</p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="close()" class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Cancel</button>
                <button type="button" @click="continueToPasswordConfirmation()" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Continue</button>
            </div>
        </div>
    </div>
</div>
