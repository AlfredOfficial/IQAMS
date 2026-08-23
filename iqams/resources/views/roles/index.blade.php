<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Roles
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            <div class="mb-6 flex items-start gap-3 bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-lg text-sm">
                <x-heroicon-o-information-circle class="w-5 h-5 shrink-0 mt-0.5" aria-hidden="true" />
                <p>Roles are fixed system values tied to login and access control. This can't be added, renamed, or removed here.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @php
                    $roleStyles = [
                        'admin' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-600', 'ring' => 'ring-indigo-100'],
                        'instructor' => ['bg' => 'bg-blue-50', 'text' => 'text-blue-600', 'ring' => 'ring-blue-100'],
                        'staff' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-600', 'ring' => 'ring-amber-100'],
                        'student' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-600', 'ring' => 'ring-emerald-100'],
                    ];
                @endphp

                @foreach ($roles as $role)
                    @php $style = $roleStyles[$role->role_name] ?? ['bg' => 'bg-gray-50', 'text' => 'text-gray-600', 'ring' => 'ring-gray-100']; @endphp
                    <div class="bg-white shadow-sm rounded-lg p-5 ring-1 {{ $style['ring'] }}">
                        <div class="w-10 h-10 rounded-lg {{ $style['bg'] }} {{ $style['text'] }} flex items-center justify-center mb-4">
                            @switch($role->role_name)
                                @case('admin')
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 2l7 4v6c0 5-3.5 8.5-7 10-3.5-1.5-7-5-7-10V6l7-4z" />
                                    </svg>
                                    @break
                                @case('instructor')
                                    <x-heroicon-o-academic-cap class="w-5 h-5" aria-hidden="true" />
                                    @break
                                @case('staff')
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-8.13a4 4 0 110 8 4 4 0 010-8zM17 8a3 3 0 110 6" />
                                    </svg>
                                    @break
                                @default
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z" />
                                    </svg>
                            @endswitch
                        </div>

                        <p class="text-2xl font-semibold text-gray-800">{{ $role->users_count }}</p>
                        <p class="text-sm text-gray-500 mt-0.5">{{ ucfirst($role->role_name) }}{{ $role->users_count === 1 ? '' : 's' }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
