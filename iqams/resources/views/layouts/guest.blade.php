<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'IQAMS') }} | Login</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
        <main class="grid min-h-screen lg:grid-cols-2">
            <section class="relative hidden overflow-hidden bg-slate-950 px-12 py-12 text-white lg:flex lg:flex-col">
                <div class="absolute inset-0 opacity-90" style="background-image: radial-gradient(circle at 15% 20%, rgba(20, 184, 166, .34), transparent 30%), radial-gradient(circle at 80% 80%, rgba(59, 130, 246, .35), transparent 34%);"></div>
                <div class="absolute -right-24 top-24 h-80 w-80 rounded-full border border-white/10"></div>
                <div class="absolute -bottom-40 -left-20 h-96 w-96 rounded-full border border-white/10"></div>

                <a href="/" class="relative z-10 inline-flex items-center gap-3 self-start">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-400 text-slate-950 shadow-lg shadow-teal-400/20">
                        <x-application-logo class="h-6 w-6" />
                    </span>
                    <span class="text-lg font-bold tracking-tight">IQAMS</span>
                </a>

                <div class="relative z-10 my-auto max-w-lg">
                    <span class="mb-6 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-xs font-medium text-teal-100 backdrop-blur">
                        <span class="h-1.5 w-1.5 rounded-full bg-teal-300"></span>
                        Attendance made simple
                    </span>
                    <h1 class="text-4xl font-bold leading-tight tracking-tight xl:text-5xl">Every presence tells a story.</h1>
                    <p class="mt-5 max-w-md text-base leading-7 text-slate-300">Manage attendance, stay on schedule, and keep your academic community connected in one place.</p>
                </div>

                <div class="relative z-10 grid grid-cols-3 gap-3 text-sm">
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
                        <p class="text-2xl font-bold text-white">Fast</p>
                        <p class="mt-1 text-xs text-slate-300">Daily check-ins</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
                        <p class="text-2xl font-bold text-white">Clear</p>
                        <p class="mt-1 text-xs text-slate-300">Live records</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
                        <p class="text-2xl font-bold text-white">Secure</p>
                        <p class="mt-1 text-xs text-slate-300">Your data protected</p>
                    </div>
                </div>
            </section>

            <section class="flex min-h-screen items-center justify-center px-5 py-10 sm:px-8">
                <div class="w-full max-w-md">
                    <a href="/" class="mb-12 inline-flex items-center gap-3 lg:hidden">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-500 text-white shadow-lg shadow-teal-500/20">
                            <x-application-logo class="h-6 w-6" />
                        </span>
                        <span class="text-lg font-bold tracking-tight">IQAMS</span>
                    </a>
                    {{ $slot }}
                    <p class="mt-10 text-center text-xs text-slate-400">&copy; {{ date('Y') }} IQAMS &middot; Integrated QR-Code Attendance Monitoring System</p>
                </div>
            </section>
        </main>
    </body>
</html>
