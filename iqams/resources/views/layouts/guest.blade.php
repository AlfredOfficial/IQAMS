<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'IQAMS') }} | Login</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <main class="relative flex min-h-screen overflow-hidden bg-slate-950"
        style="background-image: url('{{ asset('images/dtc-building.jpg') }}'); background-position: center; background-size: cover;">
        <div class="absolute inset-0 bg-slate-950/70"></div>
        <div class="absolute inset-0 bg-gradient-to-br from-slate-950/30 via-slate-950/20 to-teal-950/50"></div>

        <section class="pointer-events-none absolute inset-0 hidden px-12 py-12 text-white lg:flex lg:flex-col">
            <a href="/" class="pointer-events-auto relative z-10 inline-flex items-center gap-3 self-start">
                <span
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-400 text-slate-950 shadow-lg shadow-teal-400/20">
                    <x-application-logo class="h-6 w-6" />
                </span>
                <span class="text-lg font-bold tracking-tight">IQAMS</span>
            </a>

            <div class="relative z-10 my-auto max-w-lg pt-16">
                <p class="border-l-2 border-teal-300 pl-5 text-3xl font-bold leading-tight tracking-tight text-white lg:text-4xl xl:text-5xl">
                    “Every scan records a presence. Every presence counts.”
                </p>
                <p class="mt-5 max-w-md pl-5 text-base leading-7 text-slate-200">
                    “IQAMS makes attendance simple, accurate, and connected.”
                </p>
            </div>
        </section>

        <section class="relative z-10 flex min-h-screen w-full items-center justify-center px-5 py-8 sm:px-8 lg:justify-end lg:px-12 xl:px-20">
            <div class="w-full max-w-[31.25rem]">
                <a href="/" class="mb-8 inline-flex items-center gap-3 lg:hidden">
                    <span
                        class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-500 text-white shadow-lg shadow-teal-500/20">
                        <x-application-logo class="h-6 w-6" />
                    </span>
                    <span class="text-lg font-bold tracking-tight">IQAMS</span>
                </a>
                {{ $slot }}
                <p class="mt-8 text-center text-xs leading-5 text-slate-300">&copy; 2026 IQAMS &middot; Integrated QR-Code Attendance Monitoring System</p>
            </div>
        </section>
    </main>
    <x-logout-confirmation />
</body>

</html>
