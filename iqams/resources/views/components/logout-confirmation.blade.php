@auth
    <x-modal name="confirm-logout" max-width="sm" centered focusable>
        <section
            class="p-6 sm:p-7"
            role="dialog"
            aria-modal="true"
            aria-labelledby="logout-confirmation-title"
        >
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-50 text-blue-600">
                <x-heroicon-o-arrow-right-start-on-rectangle class="h-6 w-6" aria-hidden="true" />
            </div>

            <h2 id="logout-confirmation-title" class="mt-4 text-center text-lg font-semibold text-slate-900">
                Are you sure you want to logout?
            </h2>

            <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-center">
                <button
                    type="button"
                    x-on:click="$dispatch('close-modal', 'confirm-logout')"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 sm:w-auto"
                >
                    No, Cancel
                </button>

                <form method="POST" action="{{ route('logout') }}" data-logout-confirmed class="w-full sm:w-auto">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                    >
                        Yes, Logout
                    </button>
                </form>
            </div>
        </section>
    </x-modal>
@endauth
