@props(['active' => false, 'collapsible' => true, 'label'])

@php
$base = 'flex items-center gap-3 px-3 py-2 rounded-md text-sm transition-colors duration-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500';
$state = $active
    ? 'bg-indigo-50 text-indigo-700 font-medium'
    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900';
@endphp

<div class="relative group">
    <a
        {{ $attributes->merge(['class' => "$base $state"]) }}
        data-sidebar-link
        @if($collapsible) :class="sidebarCollapsed ? 'justify-center' : ''" @endif
        @if($active) aria-current="page" @endif
    >
        {{ $icon ?? '' }}

        @if (! $collapsible)
            <span>{{ $label }}</span>
        @else
            <span x-show="!sidebarCollapsed" x-cloak>{{ $label }}</span>
        @endif
    </a>

    @if ($collapsible)
        <span
            x-show="sidebarCollapsed"
            x-cloak
            class="pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-2 whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-xs text-white opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 transition-opacity duration-100 z-50"
            role="tooltip"
        >
            {{ $label }}
        </span>
    @endif
</div>
