<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'IQAMS') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50"
      x-data="{
          sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true',
          mobileOpen: false
      }"
      x-init="$watch('sidebarCollapsed', value => localStorage.setItem('sidebarCollapsed', value))"
      @keydown.escape.window="mobileOpen = false"
>
    <div class="min-h-screen">

        <x-sidebar />

        {{-- Mobile top bar: only visible below lg, gives access to the drawer toggle --}}
        <div class="lg:hidden sticky top-0 z-30 flex items-center justify-between bg-white border-b border-gray-200 px-4 py-3">
            <span class="font-semibold text-gray-800">{{ config('app.name', 'IQAMS') }}</span>
            <button
                @click="mobileOpen = true"
                type="button"
                class="p-2 rounded-md text-gray-500 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                aria-label="Open navigation menu"
            >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        {{-- Main content: left margin reacts to sidebar width on desktop, no margin on mobile --}}
        <div
            class="transition-[margin-left] duration-200 ease-in-out"
            :class="sidebarCollapsed ? 'lg:ml-[80px]' : 'lg:ml-[260px]'"
        >
            @isset($header)
                <header class="bg-white shadow-sm">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main>
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>