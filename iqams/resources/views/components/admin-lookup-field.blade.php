@props([
    'endpoint',
    'name',
    'model',
    'placeholder' => 'Search...',
    'emptyLabel' => '-- Select --',
    'lookupKey' => null,
    'selected' => null,
    'selectedLabel' => null,
])

<div x-data="lookupField({ endpoint: {{ Illuminate\Support\Js::from($endpoint) }}, selected: {{ Illuminate\Support\Js::from($selected) }}, selectedLabel: {{ Illuminate\Support\Js::from($selectedLabel) }} })"
     @lookup-refresh.window="if (!{{ Illuminate\Support\Js::from($lookupKey) }} || $event.detail?.key === {{ Illuminate\Support\Js::from($lookupKey) }}) load($event.detail?.values?.[{{ Illuminate\Support\Js::from($name) }}])"
     class="space-y-2">
    <input type="search"
           x-model="search"
           @focus="load()"
           @input.debounce.250ms="load()"
           placeholder="{{ $placeholder }}"
           autocomplete="off"
           class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
    <select name="{{ $name }}"
            x-ref="select"
            x-model="{{ $model }}"
            @focus="load()"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="">{{ $emptyLabel }}</option>
        <template x-for="option in options" :key="option.id">
            <option :value="String(option.id)" x-text="option.label"></option>
        </template>
    </select>
    <p x-show="loading" x-cloak class="text-xs text-gray-400">Loading options...</p>
    <p x-show="!loading && searched && options.length === 0" x-cloak class="text-xs text-gray-400">No matches found.</p>
</div>
