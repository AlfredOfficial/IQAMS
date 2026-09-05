<div x-data="{
        show: false,
        password: '',
        error: '',
        loading: false,
        form: null,
        requestConfirmation(form) {
            this.form = form;
            this.password = '';
            this.error = '';
            this.show = true;
            this.$nextTick(() => this.$refs.password.focus());
        },
        close(force = false) {
            if (this.loading && !force) return;
            this.show = false;
            this.form = null;
            this.password = '';
            this.error = '';
        },
        async confirm() {
            if (this.loading) return;
            this.loading = true;
            this.error = '';

            try {
                const response = await fetch('{{ route('password.confirm') }}', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                    },
                    body: new URLSearchParams({ password: this.password }),
                });

                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    this.error = payload.errors?.password?.[0] || 'Unable to confirm your password.';
                    return;
                }

                const pendingForm = this.form;
                this.close(true);
                pendingForm?.submit();
            } catch (error) {
                this.error = 'Unable to confirm your password. Please try again.';
            } finally {
                this.loading = false;
            }
        },
    }"
    @password-confirmation-required.window="requestConfirmation($event.detail.form)"
    @keydown.escape.window="close()">
    <div x-show="show" x-cloak x-transition.opacity class="fixed inset-0 z-[110] flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="password-confirmation-title">
        <div @click.outside="close()" x-transition class="w-full max-w-sm rounded-xl bg-white p-6 shadow-2xl">
            <h2 id="password-confirmation-title" class="text-lg font-semibold text-gray-900">Confirm your password</h2>
            <p class="mt-2 text-sm text-gray-600">Enter your current password to continue with this account change.</p>

            <form class="mt-5" @submit.prevent="confirm()">
                <label for="password-confirmation-password" class="block text-sm font-medium text-gray-700">Password</label>
                <input x-ref="password" id="password-confirmation-password" x-model="password" type="password" autocomplete="current-password" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <p x-show="error" x-text="error" class="mt-2 text-sm text-red-600"></p>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="close()" :disabled="loading" class="rounded-md px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 disabled:opacity-50">Cancel</button>
                    <button type="submit" :disabled="loading" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"><span x-text="loading ? 'Confirming…' : 'Confirm'"></span></button>
                </div>
            </form>
        </div>
    </div>
</div>
