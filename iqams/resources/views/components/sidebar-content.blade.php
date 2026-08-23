@props(['collapsible' => true])

{{-- Logo + system name --}}
<div class="flex h-20 shrink-0 items-center border-b border-gray-200 px-4"
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
            <x-heroicon-o-chevron-double-left class="w-5 h-5" aria-hidden="true" />
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
        <x-heroicon-o-chevron-double-right class="w-5 h-5" aria-hidden="true" />
    </button>
@endif

{{-- Navigation links --}}
<nav data-sidebar-nav class="flex-1 overflow-y-auto px-2 py-4 space-y-1" aria-label="Sidebar">
    <x-sidebar-link
        :href="route('dashboard')"
        :active="request()->routeIs('dashboard', 'admin.dashboard', 'instructor.dashboard', 'student.dashboard', 'staff.dashboard')"
        :collapsible="$collapsible"
        label="Dashboard"
    >
        <x-slot name="icon">
            <x-heroicon-o-home class="w-5 h-5 shrink-0" aria-hidden="true" />
        </x-slot>
    </x-sidebar-link>

    @if (in_array(Auth::user()->role?->role_name, ['staff', 'student']))
        <x-sidebar-link :href="route('leave-requests.index')" :active="request()->routeIs('leave-requests.*')" :collapsible="$collapsible" label="Leave Requests">
            <x-slot name="icon"><x-heroicon-o-bookmark class="w-5 h-5" /></x-slot>
        </x-sidebar-link>
    @endif

    @if (Auth::user()->role?->role_name === 'instructor')
        @foreach([
            'instructor.attendance' => 'My Attendance',
            'instructor.schedule' => 'My Teaching Schedule',
            'instructor.history' => 'Attendance History',
            'instructor.summary' => 'Monthly Summary',
            'instructor.issues' => 'Attendance Issues',
            'my-profile.edit' => 'Profile',
        ] as $routeName => $label)
            <x-sidebar-link :href="route($routeName)" :active="request()->routeIs($routeName)" :collapsible="$collapsible" :label="$label">
                <x-slot name="icon"><svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M5 5h14v14H5z"/></svg></x-slot>
            </x-sidebar-link>
        @endforeach
    @endif

    @if (Auth::user()->role?->role_name === 'admin')
        <x-sidebar-link :href="route('admin.leave-requests.index')" :active="request()->routeIs('admin.leave-requests.*')" :collapsible="$collapsible" label="Leave Requests">
            <x-slot name="icon"><x-heroicon-o-bookmark class="w-5 h-5" /></x-slot>
        </x-sidebar-link>
        <x-sidebar-link
            :href="route('attendance-scanner.index')"
            :active="request()->routeIs('attendance-scanner.*')"
            :collapsible="$collapsible"
            target="_self"
            label="QR Scanner"
        >
            <x-slot name="icon">
                <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7V5a2 2 0 012-2h2m10 0h2a2 2 0 012 2v2M3 17v2a2 2 0 002 2h2m10 0h2a2 2 0 002-2v-2M7 7h3v3H7V7zm7 0h3v3h-3V7zM7 14h3v3H7v-3zm7 0h3v3h-3v-3z" />
                </svg>
            </x-slot>
        </x-sidebar-link>

        <x-sidebar-link
            :href="route('attendance-logs.index')"
            :active="request()->routeIs('attendance-logs.*')"
            :collapsible="$collapsible"
            label="Attendance Logs"
        >
            <x-slot name="icon">
                <x-heroicon-o-check-badge class="w-5 h-5 shrink-0" aria-hidden="true" />
            </x-slot>
        </x-sidebar-link>
        <x-sidebar-link :href="route('school-events.index')" :active="request()->routeIs('school-events.*')" :collapsible="$collapsible" label="School Events">
            <x-slot name="icon"><x-heroicon-o-calendar-days class="h-5 w-5" /></x-slot>
        </x-sidebar-link>

        <div class="px-3 pt-5 pb-2" @if($collapsible) x-show="!sidebarCollapsed" x-cloak @endif>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">User Management</p>
        </div>
        <x-sidebar-link
            :href="route('instructors.index')"
            :active="request()->routeIs('instructors.*')"
            :collapsible="$collapsible"
            label="Manage Instructor"
        >
            <x-slot name="icon">
                <x-heroicon-o-users class="w-5 h-5 shrink-0" aria-hidden="true" />
            </x-slot>
        </x-sidebar-link>
        <x-sidebar-link
            :href="route('students.index')"
            :active="request()->routeIs('students.*')"
            :collapsible="$collapsible"
            label="Manage Student"
        >
            <x-slot name="icon">
                <x-heroicon-o-academic-cap class="w-5 h-5 shrink-0" aria-hidden="true" />
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

        <div class="px-3 pt-5 pb-2" @if($collapsible) x-show="!sidebarCollapsed" x-cloak @endif>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Academic Management</p>
        </div>
        <x-sidebar-link
            :href="route('schedules.index')"
            :active="request()->routeIs('schedules.*')"
            :collapsible="$collapsible"
            label="Manage Schedule"
        >
            <x-slot name="icon">
                <x-heroicon-o-calendar class="w-5 h-5 shrink-0" aria-hidden="true" />
            </x-slot>
        </x-sidebar-link>
        <x-sidebar-link
            :href="route('departments.index')"
            :active="request()->routeIs('departments.*')"
            :collapsible="$collapsible"
            label="Manage Department"
        >
            <x-slot name="icon">
                <x-heroicon-o-building-office-2 class="w-5 h-5 shrink-0" aria-hidden="true" />
            </x-slot>
        </x-sidebar-link>
        <x-sidebar-link
            :href="route('courses.index')"
            :active="request()->routeIs('courses.*')"
            :collapsible="$collapsible"
            label="Manage Course"
        >
            <x-slot name="icon">
                <x-heroicon-o-book-open class="w-5 h-5 shrink-0" aria-hidden="true" />
            </x-slot>
        </x-sidebar-link>
        <x-sidebar-link
            :href="route('subjects.index')"
            :active="request()->routeIs('subjects.*')"
            :collapsible="$collapsible"
            label="Manage Subject"
        >
            <x-slot name="icon">
                <x-heroicon-o-book-open class="w-5 h-5 shrink-0" aria-hidden="true" />
            </x-slot>
        </x-sidebar-link>
        <x-sidebar-link
            :href="route('sections.index')"
            :active="request()->routeIs('sections.*')"
            :collapsible="$collapsible"
            label="Manage Section"
        >
            <x-slot name="icon">
                <x-heroicon-o-view-columns class="w-5 h-5 shrink-0" aria-hidden="true" />
            </x-slot>
        </x-sidebar-link>
        <x-sidebar-link
            :href="route('roles.index')"
            :active="request()->routeIs('roles.*')"
            :collapsible="$collapsible"
            label="Roles"
        >
            <x-slot name="icon">
                <x-heroicon-o-check-circle class="w-5 h-5 shrink-0" aria-hidden="true" />
            </x-slot>
        </x-sidebar-link>
    @endif
</nav>

{{-- User info + logout footer --}}
{{-- User menu: click avatar/name to reveal Profile + Logout --}}
<div class="border-t border-gray-200 p-3 shrink-0" x-data="{ userMenuOpen: false }">
    <button
        type="button"
        @click="userMenuOpen = !userMenuOpen"
        class="w-full flex items-center gap-3 px-1 py-2 rounded-md hover:bg-gray-50 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
        @if($collapsible) :class="sidebarCollapsed ? 'justify-center' : ''" @endif
        aria-label="Open user menu"
        :aria-expanded="userMenuOpen"
    >
        @if (Auth::user()->avatar_url)
            <img src="{{ Auth::user()->avatar_url }}" alt="" class="w-8 h-8 rounded-full object-cover shrink-0">
        @else
            <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-sm font-medium shrink-0">
                {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
            </div>
        @endif

        @if (! $collapsible)
            <div class="min-w-0 text-left flex-1">
                <div class="text-sm font-medium text-gray-800 truncate">{{ Auth::user()->name }}</div>
                <div class="text-xs text-gray-400 truncate">{{ ucfirst(Auth::user()->role?->role_name ?? 'No role') }}</div>
            </div>
            <x-heroicon-o-chevron-down class="w-4 h-4 text-gray-400 shrink-0 transition-transform" x-bind:class="userMenuOpen ? 'rotate-180' : ''" aria-hidden="true" />
        @else
            <div x-show="!sidebarCollapsed" x-cloak class="min-w-0 text-left flex-1">
                <div class="text-sm font-medium text-gray-800 truncate">{{ Auth::user()->name }}</div>
                <div class="text-xs text-gray-400 truncate">{{ ucfirst(Auth::user()->role?->role_name ?? 'No role') }}</div>
            </div>
            <x-heroicon-o-chevron-down x-show="!sidebarCollapsed" x-cloak class="w-4 h-4 text-gray-400 shrink-0 transition-transform" x-bind:class="userMenuOpen ? 'rotate-180' : ''" aria-hidden="true" />
        @endif
    </button>

    <div x-show="userMenuOpen"
         x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="mt-1 space-y-1">
        <x-sidebar-link
            :href="route(Auth::user()->role?->role_name === 'admin' ? 'profile.edit' : 'my-profile.edit')"
            :active="request()->routeIs(Auth::user()->role?->role_name === 'admin' ? 'profile.edit' : 'my-profile.edit')"
            :collapsible="$collapsible"
            label="Profile"
        >
            <x-slot name="icon">
                <x-heroicon-o-user class="w-5 h-5 shrink-0" aria-hidden="true" />
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
                    <x-heroicon-o-arrow-right-start-on-rectangle class="w-5 h-5 shrink-0" aria-hidden="true" />
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
</div>
