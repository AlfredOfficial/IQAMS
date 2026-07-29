@props(['collapsible' => true])

{{-- Logo + system name --}}
<div class="flex items-center h-16 px-4 border-b border-gray-200 shrink-0"
     @if($collapsible) :class="sidebarCollapsed ? 'justify-center' : 'justify-between'" @else class="justify-between" @endif
>
    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 min-w-0" aria-label="{{ config('app.name', 'IQAMS') }} home">
        <x-application-logo class="h-8 w-8 fill-current text-indigo-600 shrink-0" />
        @if (! $collapsible)
            <span class="font-semibold text-gray-800 truncate">{{ config('app.name', 'IQAMS') }}</span>
        @else
            <span x-show="!sidebarCollapsed" x-cloak class="font-semibold text-gray-800 truncate">{{ config('app.name', 'IQAMS') }}</span>
        @endif
    </a>

    @if ($collapsible)
        <button
            @click="sidebarCollapsed = !sidebarCollapsed"
            x-show="!sidebarCollapsed"
            x-cloak
            type="button"
            class="p-1.5 rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 shrink-0"
            :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
            </svg>
        </button>
    @endif
</div>

{{-- Expand button, shown only as a centered icon when collapsed --}}
@if ($collapsible)
    <button
        @click="sidebarCollapsed = !sidebarCollapsed"
        x-show="sidebarCollapsed"
        x-cloak
        type="button"
        class="mx-auto mt-2 p-1.5 rounded-md text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        aria-label="Expand sidebar"
    >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
        </svg>
    </button>
@endif

{{-- Navigation links --}}
<nav class="flex-1 overflow-y-auto px-2 py-4 space-y-1" aria-label="Sidebar">
    <x-sidebar-link
        :href="route('dashboard')"
        :active="request()->routeIs('dashboard')"
        :collapsible="$collapsible"
        label="Dashboard"
    >
        <x-slot name="icon">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
            </svg>
        </x-slot>
    </x-sidebar-link>

    @if (Auth::user()->role?->role_name === 'admin')
        <x-sidebar-link
            :href="route('instructors.index')"
            :active="request()->routeIs('instructors.*')"
            :collapsible="$collapsible"
            label="Manage Instructor"
        >
            <x-slot name="icon">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
            </x-slot>
        </x-sidebar-link>
        <x-sidebar-link
            :href="route('non-teaching-staff.index')"
            :active="request()->routeIs('non-teaching-staff.*')"
            :collapsible="$collapsible"
            label="Manage Staff"
        >
            <x-slot name="icon">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-8.13a4 4 0 110 8 4 4 0 010-8zM17 8a3 3 0 110 6" />
                </svg>
            </x-slot>
        </x-sidebar-link>
        <x-sidebar-link
            :href="route('departments.index')"
            :active="request()->routeIs('departments.*')"
            :collapsible="$collapsible"
            label="Manage Department"
        >
            <x-slot name="icon">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4M9 9h.01M9 12h.01M9 15h.01" />
                </svg>
            </x-slot>
        </x-sidebar-link>
        <x-sidebar-link
            :href="route('courses.index')"
            :active="request()->routeIs('courses.*')"
            :collapsible="$collapsible"
            label="Manage Course"
        >
            <x-slot name="icon">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s4.332.477 5.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </x-slot>
        </x-sidebar-link>
        <x-sidebar-link
            :href="route('subjects.index')"
            :active="request()->routeIs('subjects.*')"
            :collapsible="$collapsible"
            label="Manage Subject"
        >
            <x-slot name="icon">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s4.332.477 5.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                </svg>
            </x-slot>
        </x-sidebar-link>
        <x-sidebar-link
            :href="route('roles.index')"
            :active="request()->routeIs('roles.*')"
            :collapsible="$collapsible"
            label="Roles"
        >
            <x-slot name="icon">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </x-slot>
        </x-sidebar-link>
    @endif
</nav>

{{-- User info + logout footer --}}
<div class="border-t border-gray-200 p-3 shrink-0">
    <div class="flex items-center gap-3 px-1 py-2"
         @if($collapsible) :class="sidebarCollapsed ? 'justify-center' : ''" @endif
    >
        <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-medium shrink-0">
            {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
        </div>
        @if (! $collapsible)
            <div class="min-w-0">
                <div class="text-sm font-medium text-gray-800 truncate">{{ Auth::user()->name }}</div>
                <div class="text-xs text-gray-400 truncate">{{ ucfirst(Auth::user()->role?->role_name ?? 'No role') }}</div>
            </div>
        @else
            <div x-show="!sidebarCollapsed" x-cloak class="min-w-0">
                <div class="text-sm font-medium text-gray-800 truncate">{{ Auth::user()->name }}</div>
                <div class="text-xs text-gray-400 truncate">{{ ucfirst(Auth::user()->role?->role_name ?? 'No role') }}</div>
            </div>
        @endif
    </div>

    <x-sidebar-link
        :href="route('profile.edit')"
        :active="request()->routeIs('profile.edit')"
        :collapsible="$collapsible"
        label="Profile"
    >
        <x-slot name="icon">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
        </x-slot>
    </x-sidebar-link>

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <div class="relative group">
            <button
                type="submit"
                class="w-full flex items-center gap-3 px-3 py-2 rounded-md text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                @if($collapsible) :class="sidebarCollapsed ? 'justify-center' : ''" @endif
                aria-label="Log out"
            >
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                @if (! $collapsible)
                    <span>Log Out</span>
                @else
                    <span x-show="!sidebarCollapsed" x-cloak>Log Out</span>
                @endif
            </button>

            @if ($collapsible)
                <span
                    x-show="sidebarCollapsed"
                    x-cloak
                    class="pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 group-hover:opacity-100 transition-opacity"
                    role="tooltip"
                >
                    Log Out
                </span>
            @endif
        </div>
    </form>
</div>