@php
    $notifications = [];

    if (session('success')) {
        $notifications[] = ['type' => 'success', 'title' => 'Success', 'message' => session('success')];
    }

    if (session('error')) {
        $notifications[] = ['type' => 'error', 'title' => 'Error', 'message' => session('error')];
    }

    if (session('warning')) {
        $notifications[] = ['type' => 'warning', 'title' => 'Warning', 'message' => session('warning')];
    }

    if (session('info')) {
        $notifications[] = ['type' => 'info', 'title' => 'Information', 'message' => session('info')];
    }

    if (session('generated_password')) {
        $notifications[] = ['type' => 'info', 'title' => 'Temporary password', 'message' => 'Provide this password to the user: ' . session('generated_password')];
    }

    if (session('status') === 'profile-updated') {
        $notifications[] = ['type' => 'success', 'title' => 'Profile Updated', 'message' => 'Your profile was updated successfully.'];
    }

    if (session('status') === 'password-updated') {
        $notifications[] = ['type' => 'success', 'title' => 'Password Updated', 'message' => 'Your password has been changed successfully.'];
    }

    if (session('status') === 'verification-link-sent') {
        $notifications[] = ['type' => 'info', 'title' => 'Verification Email Sent', 'message' => 'A new verification link has been sent to your email address.'];
    }
@endphp

<div
    x-data="toastNotifications(@js($notifications))"
    data-toast-notifications="{{ json_encode($notifications) }}"
    @toast.window="add($event.detail)"
    class="pointer-events-none fixed right-3 top-3 z-[100] flex w-[calc(100%-1.5rem)] max-w-sm flex-col gap-3 sm:right-5 sm:top-5"
    role="region"
    aria-label="Notifications"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="toast.visible"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-x-6"
            x-transition:enter-end="opacity-100 translate-x-0"
            x-transition:leave="transition ease-in duration-250"
            x-transition:leave-start="opacity-100 translate-x-0"
            x-transition:leave-end="opacity-0 translate-x-6"
            class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white px-4 py-3.5 shadow-lg shadow-slate-900/10"
            :class="{
                'border-emerald-200': toast.type === 'success',
                'border-red-200':     toast.type === 'error',
                'border-amber-200':   toast.type === 'warning',
                'border-sky-200':     toast.type === 'info',
            }"
            role="status"
            aria-live="polite"
        >
            <span
                class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full"
                :class="{
                    'bg-emerald-100 text-emerald-700': toast.type === 'success',
                    'bg-red-100 text-red-700':         toast.type === 'error',
                    'bg-amber-100 text-amber-700':     toast.type === 'warning',
                    'bg-sky-100 text-sky-700':         toast.type === 'info',
                }"
                aria-hidden="true"
            >
                {{-- Alpine x-if cannot render Blade components, so we use inline SVG paths per type --}}
                <svg x-show="toast.type === 'success'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                <svg x-show="toast.type === 'error'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <svg x-show="toast.type === 'warning'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /></svg>
                <svg x-show="toast.type === 'info'" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-slate-900" x-text="toast.title"></span>
                <span class="mt-0.5 block break-words text-sm leading-5 text-slate-600" x-text="toast.message"></span>
            </span>
        </div>
    </template>
</div>
