@props([
    'show',
    'close',
    'zIndex' => 'z-[90]',
    'scrollable' => false,
])

<div
    x-show="{{ $show }}"
    x-cloak
    x-transition.opacity
    @keydown.escape.window="{{ $close }}"
    @click.self="{{ $close }}"
    {{ $attributes->class([
        'fixed inset-0 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm',
        $zIndex,
        'overflow-y-auto' => $scrollable,
    ]) }}
>
    {{ $slot }}
</div>
