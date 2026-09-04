@php
    $portalUser = Auth::user();
    $portalStaff = $portalUser->nonTeachingStaff;
    $portalName = $portalStaff?->fullName() ?? $portalUser->name;
    $portalGreeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 18 ? 'Good afternoon' : 'Good evening');
    $initials = collect(explode(' ', $portalName))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('');
    $nav = [
        ['route' => 'staff.dashboard', 'label' => 'Dashboard', 'icon' => 'M4 5a2 2 0 012-2h3a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm9 0a2 2 0 012-2h3a2 2 0 012 2v7a2 2 0 01-2 2h-3a2 2 0 01-2-2V5zM4 16a2 2 0 012-2h3a2 2 0 012 2v3a2 2 0 01-2 2H6a2 2 0 01-2-2v-3zm9 1a2 2 0 012-2h3a2 2 0 012 2v2a2 2 0 01-2 2h-3a2 2 0 01-2-2v-2z'],
        ['route' => 'staff.attendance.history', 'label' => 'Attendance History', 'icon' => 'M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['route' => 'staff.attendance.summary', 'label' => 'Monthly Summary', 'icon' => 'M4 19h16M7 16V9m5 7V5m5 11v-4'],
        ['route' => 'staff.attendance.issues', 'label' => 'Attendance Issues', 'icon' => 'M12 9v4m0 4h.01M10.3 3.6L2.6 17a2 2 0 001.7 3h15.4a2 2 0 001.7-3L13.7 3.6a2 2 0 00-3.4 0z'],
        ['route' => 'staff.leave-requests.index', 'active' => 'staff.leave-requests.*', 'label' => 'Leave Requests', 'icon' => 'M7 3h10a2 2 0 012 2v16l-7-3-7 3V5a2 2 0 012-2z'],
        ['route' => 'staff.profile.edit', 'label' => 'Profile', 'icon' => 'M5.1 17.8a9 9 0 1113.8 0M15 11a3 3 0 11-6 0 3 3 0 016 0z'],
    ];
@endphp

<x-portal-document
    :title="$title"
    body-class="bg-[#f5f7fb] font-sans text-slate-800 antialiased"
    alpine-data="{ sidebarOpen: false, userMenuOpen: false }"
    x-on:keydown.escape.window="sidebarOpen=false;userMenuOpen=false"
>
    <x-toast-notifications />
    <div class="min-h-screen lg:flex">
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen=false" class="fixed inset-0 z-40 bg-slate-950/50 lg:hidden"></div>
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 flex w-[218px] flex-col bg-[#10294b] text-white shadow-2xl transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
            <div class="flex h-[92px] items-center gap-3 px-5">
                <div class="grid h-11 w-11 place-items-center rounded-full border-2 border-sky-300 bg-blue-600 text-xl font-extrabold shadow-lg shadow-blue-950/40">I</div>
                <div><p class="text-[26px] font-extrabold leading-none tracking-tight">IQAMS</p><p class="mt-1 text-xs font-medium text-blue-100">Staff Portal</p></div>
                <button type="button" @click="sidebarOpen=false" class="ml-auto text-2xl lg:hidden" aria-label="Close navigation">&times;</button>
            </div>

            <nav data-sidebar-nav class="flex-1 space-y-2 overflow-y-auto px-3 py-3" aria-label="Staff navigation">
                @foreach ($nav as $item)
                    @php($active = request()->routeIs($item['active'] ?? $item['route']))
                    <a data-sidebar-link href="{{ route($item['route']) }}" @class(['flex items-center gap-3 rounded-xl px-3 py-3.5 text-[13px] font-semibold transition', 'bg-blue-600 text-white shadow-lg shadow-blue-950/25' => $active, 'text-blue-50/90 hover:bg-white/10' => ! $active])>
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $item['icon'] }}"/></svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="px-6 pb-3 text-center text-xs italic leading-5 text-blue-100/65">Consistency is the key<br>to excellence.</div>
            <form method="POST" action="{{ route('logout') }}" class="border-t border-white/10 p-3">
                @csrf
                <button class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-sm text-blue-50/85 hover:bg-white/10"><x-heroicon-o-arrow-right-start-on-rectangle class="h-5 w-5" />Logout</button>
            </form>
        </aside>

        <div class="min-w-0 flex-1">
            <header class="flex min-h-[92px] items-center bg-white px-4 sm:px-7 lg:px-8">
                <button type="button" @click="sidebarOpen=true" class="mr-4 rounded-lg bg-slate-50 p-2.5 text-[#15355e] hover:bg-slate-100 lg:hidden" aria-label="Open navigation"><x-heroicon-o-bars-3 class="h-6 w-6" /></button>
                <div class="min-w-0"><h1 class="truncate text-lg font-extrabold text-slate-950 sm:text-xl">{{ $title === 'Dashboard' ? $portalGreeting.', '.$portalName.'!' : $title }}</h1><p class="mt-1 text-xs text-slate-500 sm:text-sm">Non-Teaching Personnel <span class="mx-2">•</span> {{ $portalStaff?->officeUnit?->name ?? 'Office/unit not assigned' }}</p></div>
                <div class="ml-auto flex items-center gap-4 sm:gap-6">
                    <div class="hidden rounded-xl bg-slate-50 px-4 py-2.5 text-sm text-[#17345c] ring-1 ring-slate-200/80 md:block">
                        <p class="flex items-center gap-2 font-semibold"><x-heroicon-o-calendar-days class="h-4 w-4 text-blue-600" />{{ now()->format('l, F j, Y') }}</p>
                    </div>
                    <x-leave-notification-bell />
                     <a href="{{ route('staff.profile.edit') }}" class="relative h-12 w-12 overflow-hidden rounded-full bg-blue-100 text-blue-800 ring-1 ring-slate-200" aria-label="Open profile">@if($portalUser->avatar_thumbnail_url)<img loading="lazy" width="48" height="48" src="{{ $portalUser->avatar_thumbnail_url }}" class="h-full w-full object-cover" alt="{{ $portalName }}">@else<span class="grid h-full place-items-center font-bold">{{ $initials }}</span>@endif</a>
                </div>
            </header>
            <main id="app-content" class="p-3 sm:p-5 lg:p-6">{{ $slot }}</main>
        </div>
    </div>
</x-portal-document>
