@php
    $notifications = [];

    if (session('success')) {
        $notifications[] = ['title' => 'Success', 'message' => session('success')];
    }

    if (session('status') === 'profile-updated') {
        $notifications[] = ['title' => 'Success', 'message' => 'Your profile was updated successfully.'];
    }
@endphp

<div
    x-data="toastNotifications(@js($notifications))"
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
            class="pointer-events-auto flex items-start gap-3 rounded-xl border border-emerald-200 bg-white px-4 py-3.5 shadow-lg shadow-slate-900/10"
            role="status"
            aria-live="polite"
        >
            <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700" aria-hidden="true">
                <x-heroicon-o-check class="h-4 w-4" />
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-semibold text-slate-900" x-text="toast.title"></span>
                <span class="mt-0.5 block break-words text-sm leading-5 text-slate-600" x-text="toast.message"></span>
            </span>
        </div>
    </template>
</div>
