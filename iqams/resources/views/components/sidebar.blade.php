{{-- Desktop sidebar --}}
<aside
    class="hidden lg:flex lg:flex-col fixed inset-y-0 left-0 z-40 bg-white border-r border-gray-200 transition-[width] duration-200 ease-in-out"
    :class="sidebarCollapsed ? 'w-[80px]' : 'w-[260px]'"
    aria-label="Main navigation"
>
    <x-sidebar-content />
</aside>

{{-- Mobile off-canvas drawer --}}
<div
    x-show="mobileOpen"
    x-cloak
    class="lg:hidden fixed inset-0 z-50"
    role="dialog"
    aria-modal="true"
    aria-label="Main navigation"
>
    {{-- Overlay --}}
    <div
        x-show="mobileOpen"
        x-transition:enter="transition-opacity ease-linear duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-linear duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-900/50"
        @click="mobileOpen = false"
        aria-hidden="true"
    ></div>

    {{-- Drawer panel --}}
    <div
        x-show="mobileOpen"
        @click.outside="mobileOpen = false"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed inset-y-0 left-0 w-[260px] bg-white border-r border-gray-200 flex flex-col"
    >
        <div class="flex justify-end px-4 pt-3">
            <button
                @click="mobileOpen = false"
                type="button"
                class="p-2 rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-label="Close navigation menu"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <x-sidebar-content :collapsible="false" />
    </div>
</div>