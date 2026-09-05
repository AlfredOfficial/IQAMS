@props([
    'deleteAction',
    'toggleAction',
    'nextStatus',
    'isActive' => true,
    'deleteName' => 'this record',
    'requiresPasswordConfirmation' => false,
])

<div class="relative inline-flex"
     x-data="{
         open: false,
         top: 0,
         right: 0,
         maxHeight: null,
         maxWidth: null,
         toggle() {
             this.open = ! this.open;

             if (this.open) {
                 this.$nextTick(() => this.positionMenu());
             }
         },
         positionMenu() {
             const trigger = this.$refs.trigger;
             const menu = this.$refs.menu;

             if (!trigger || !menu) {
                 return;
             }

             const triggerRect = trigger.getBoundingClientRect();
             const viewportPadding = 8;
             const gap = 6;
             const menuHeight = menu.scrollHeight;
             const viewportWidth = Math.max(0, window.innerWidth - (viewportPadding * 2));
             const menuWidth = Math.min(menu.scrollWidth, viewportWidth);
             const spaceAbove = Math.max(0, triggerRect.top - gap - viewportPadding);
             const spaceBelow = Math.max(0, window.innerHeight - triggerRect.bottom - gap - viewportPadding);
             const opensUpward = spaceBelow < menuHeight && spaceAbove >= spaceBelow && spaceAbove > 0;
             const availableHeight = Math.min(menuHeight, opensUpward ? spaceAbove : spaceBelow);
             const visibleHeight = Math.max(0, availableHeight);
             const preferredTop = opensUpward
                 ? triggerRect.top - gap - visibleHeight
                 : triggerRect.bottom + gap;
             const maxTop = Math.max(viewportPadding, window.innerHeight - viewportPadding - visibleHeight);
             const maxRight = Math.max(viewportPadding, window.innerWidth - viewportPadding - menuWidth);

             this.maxHeight = visibleHeight;
             this.maxWidth = viewportWidth;
             this.top = Math.min(Math.max(preferredTop, viewportPadding), maxTop);
             this.right = Math.min(
                 Math.max(window.innerWidth - triggerRect.right, viewportPadding),
                 maxRight,
             );
         },
     }"
     @resize.window="if (open) positionMenu()"
     @scroll.window.capture="if (open) positionMenu()">
    <button type="button"
            x-ref="trigger"
            @click="toggle()"
            class="inline-flex h-9 w-9 items-center justify-center rounded-md text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            aria-label="Open actions menu"
            :aria-expanded="open">
        <x-heroicon-m-ellipsis-vertical class="h-5 w-5" aria-hidden="true" />
    </button>

    <template x-teleport="body">
        <div x-show="open"
             x-ref="menu"
             x-cloak
             x-transition.opacity.duration.100ms
             @click.outside="open = false"
             @keydown.escape.window="open = false"
             :style="`top: ${top}px; right: ${right}px; max-height: ${maxHeight ? maxHeight + 'px' : 'none'}; max-width: ${maxWidth ? maxWidth + 'px' : 'none'}`"
             class="fixed z-[100] w-52 overflow-y-auto rounded-lg bg-white py-1 text-left text-sm shadow-lg ring-1 ring-black/10">
            <div @click="open = false" class="[&>button]:block [&>button]:w-full [&>button]:px-4 [&>button]:py-2.5 [&>button]:text-left [&>button]:text-gray-700 [&>button:hover]:bg-gray-50">
                {{ $edit }}
            </div>
            @isset($reset)
                <div @click="open = false" class="[&>form]:block [&>form>button]:block [&>form>button]:w-full [&>form>button]:px-4 [&>form>button]:py-2.5 [&>form>button]:text-left [&>form>button]:text-gray-700 [&>form>button:hover]:bg-gray-50">
                    {{ $reset }}
                </div>
            @endisset
            @isset($qr)
                <div @click="open = false" class="[&>button]:block [&>button]:w-full [&>button]:px-4 [&>button]:py-2.5 [&>button]:text-left [&>button]:text-gray-700 [&>button:hover]:bg-gray-50">
                    {{ $qr }}
                </div>
            @endisset

            <form method="POST" action="{{ $toggleAction }}" @if ($requiresPasswordConfirmation) @submit.prevent="open = false; $dispatch('password-confirmation-required', { form: $el })" @endif>
                @csrf
                @method('PATCH')
                <input type="hidden" name="status" value="{{ $nextStatus }}">
                <button type="submit" class="block w-full px-4 py-2.5 text-left text-gray-700 hover:bg-gray-50">
                    {{ $isActive ? 'Deactivate Account' : 'Activate Account' }}
                </button>
            </form>

            <div class="my-1 border-t border-gray-100"></div>

            <form method="POST" action="{{ $deleteAction }}" onsubmit='return confirm({{ Illuminate\Support\Js::from("Deactivate {$deleteName}? Their login will be disabled and historical attendance will be retained.") }})' @if ($requiresPasswordConfirmation) @submit.prevent="open = false; $dispatch('password-confirmation-required', { form: $el })" @endif>
                @csrf
                @method('DELETE')
                <button type="submit" class="block w-full px-4 py-2.5 text-left font-medium text-red-600 hover:bg-red-50">Deactivate / Archive</button>
            </form>
        </div>
    </template>
</div>
