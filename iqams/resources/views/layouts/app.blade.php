<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'IQAMS') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-screen overflow-hidden font-sans antialiased bg-gray-50"
      x-data="{
          sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true',
          mobileOpen: false
      }"
      x-init="$watch('sidebarCollapsed', value => localStorage.setItem('sidebarCollapsed', value))"
      @keydown.escape.window="mobileOpen = false"
      @spa-navigated.window="mobileOpen = false"
>
    <x-toast-notifications />
    <div class="h-screen overflow-hidden">

        <x-sidebar />

        {{-- Mobile top bar: only visible below lg, gives access to the drawer toggle --}}
        <div class="h-16 lg:hidden flex items-center justify-between bg-white border-b border-gray-200 px-4">
            <span class="font-semibold text-gray-800">{{ config('app.name', 'IQAMS') }}</span>
            <div class="ml-auto mr-2"><x-leave-notification-bell /></div>
            <button
                @click="mobileOpen = true"
                type="button"
                class="p-2 rounded-md text-gray-500 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-label="Open navigation menu"
            >
                <x-heroicon-o-bars-3 class="w-6 h-6" aria-hidden="true" />
            </button>
        </div>

        {{-- Main content: left margin reacts to sidebar width on desktop, no margin on mobile --}}
        <div
            id="app-content"
            class="flex h-[calc(100vh-4rem)] min-h-0 flex-col transition-[margin-left] duration-200 ease-in-out lg:h-screen"
            :class="sidebarCollapsed ? 'lg:ml-[80px]' : 'lg:ml-[260px]'"
        >
            @isset($header)
                <header class="z-30 shrink-0 border-b border-gray-200 bg-white">
                    <div class="mx-auto flex min-h-20 max-w-7xl items-center gap-4 px-4 py-3 sm:px-6 lg:px-8">
                        <div class="min-w-0 flex-1">{{ $header }}</div>
                        <x-leave-notification-bell class="hidden lg:block" />
                    </div>
                </header>
            @endisset

            <main class="min-h-0 flex-1 overflow-y-auto">
                {{ $slot }}
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
