<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} - {{ config('app.name', 'IQAMS') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|lexend:500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $portalUser = Auth::user();
    $portalStudent = $portalUser->student;
    $initials = strtoupper(substr($portalStudent?->first_name ?? $portalUser->name, 0, 1));
    $nav = [
        ['route' => 'student.dashboard', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0h6'],
        ['route' => 'student.profile', 'label' => 'Profile', 'icon' => 'M5.121 17.804A9 9 0 1118.88 17.8M15 11a3 3 0 11-6 0 3 3 0 016 0z'],
        ['route' => 'student.attendance', 'label' => 'Attendance', 'icon' => 'M9 12l2 2 4-4m5-4v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2m4-1h4a1 1 0 011 1v2H9V4a1 1 0 011-1h2z'],
        ['route' => 'student.qr', 'label' => 'QR Code', 'icon' => 'M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h2v2h-2v-2zm4 0h2v6h-6v-2h4v-4z'],
        ['route' => 'student.settings', 'label' => 'Settings', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
    ];
@endphp
<body class="font-sans antialiased bg-slate-50 text-slate-800" x-data="{ sidebarOpen: false, userMenuOpen: false }">
    <x-toast-notifications />
    <div class="min-h-screen lg:flex">
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen=false" class="fixed inset-0 z-40 bg-slate-950/40 backdrop-blur-sm lg:hidden"></div>
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-[#073c3b] text-white transition-transform duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
            <div class="flex h-20 items-center gap-3 border-b border-white/10 px-6">
                <div class="grid h-10 w-10 place-items-center rounded-xl bg-teal-400 font-display text-lg font-bold text-teal-950">I</div>
                <div><p class="font-display font-semibold">IQAMS</p><p class="text-xs text-teal-100/60">Student Portal</p></div>
                <button @click="sidebarOpen=false" class="ml-auto lg:hidden" aria-label="Close navigation">&times;</button>
            </div>
            <div class="border-b border-white/10 p-5">
                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 overflow-hidden rounded-full bg-teal-100 text-teal-800 ring-2 ring-white/20">
                        @if($portalUser->avatar_url)<img src="{{ $portalUser->avatar_url }}" alt="Profile photo" class="h-full w-full object-cover">@else<div class="grid h-full place-items-center font-semibold">{{ $initials }}</div>@endif
                    </div>
                    <div class="min-w-0"><p class="truncate text-sm font-semibold">{{ $portalStudent?->fullName() ?? $portalUser->name }}</p><p class="truncate text-xs text-teal-100/60">{{ $portalStudent?->student_no }}</p></div>
                </div>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto p-4" aria-label="Student navigation">
                @foreach($nav as $item)
                    <a href="{{ route($item['route']) }}" @class(['flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition', 'bg-white text-teal-900 shadow-sm' => request()->routeIs($item['route']), 'text-teal-50/75 hover:bg-white/10 hover:text-white' => !request()->routeIs($item['route'])])>
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $item['icon'] }}"/></svg>{{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="border-t border-white/10 p-4">@csrf<button class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-sm text-teal-50/75 hover:bg-white/10 hover:text-white"><svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>Logout</button></form>
        </aside>
        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-30 flex h-20 items-center border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-8">
                <button @click="sidebarOpen=true" class="mr-4 rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Open navigation"><svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                <div><h1 class="font-display text-lg font-semibold text-slate-900">{{ $title }}</h1><p class="hidden text-xs text-slate-400 sm:block">Welcome back, {{ $portalStudent?->first_name }}</p></div>
                <div class="relative ml-auto">
                    <button @click="userMenuOpen=!userMenuOpen" class="flex items-center gap-3 rounded-xl p-1.5 hover:bg-slate-100">
                        <span class="hidden text-right sm:block"><span class="block text-sm font-medium">{{ $portalStudent?->fullName() ?? $portalUser->name }}</span><span class="block text-xs text-slate-400">Student</span></span>
                        <span class="h-10 w-10 overflow-hidden rounded-full bg-teal-100 text-teal-800">@if($portalUser->avatar_url)<img src="{{ $portalUser->avatar_url }}" alt="" class="h-full w-full object-cover">@else<span class="grid h-full place-items-center font-semibold">{{ $initials }}</span>@endif</span>
                    </button>
                    <div x-show="userMenuOpen" x-cloak @click.outside="userMenuOpen=false" class="absolute right-0 mt-2 w-48 rounded-xl border border-slate-100 bg-white p-1 shadow-xl">
                        <a href="{{ route('student.profile') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">My profile</a>
                        <a href="{{ route('student.settings') }}" class="block rounded-lg px-3 py-2 text-sm hover:bg-slate-50">Account settings</a>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50">Logout</button></form>
                    </div>
                </div>
            </header>
            <main id="app-content" class="mx-auto max-w-7xl p-4 sm:p-8">
                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
