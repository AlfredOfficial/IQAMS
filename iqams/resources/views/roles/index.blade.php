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

            <div class="mt-8 overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h3 class="font-semibold text-gray-800">User role assignments</h3>
                    <p class="mt-1 text-sm text-gray-500">Each account must have exactly one fixed portal role.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-600"><tr><th class="px-5 py-3">User</th><th class="px-5 py-3">Username</th><th class="px-5 py-3">Role</th></tr></thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($users as $user)
                                <tr>
                                    <td class="px-5 py-3 font-medium text-gray-800">{{ $user->name }}</td>
                                    <td class="px-5 py-3 text-gray-600">{{ $user->username }}</td>
                                    <td class="px-5 py-3">
                                        <form method="POST" action="{{ route('roles.assign', $user) }}" class="flex items-center gap-2">
                                            @csrf @method('PATCH')
                                            <select name="role" class="rounded-md border-gray-300 text-sm" @disabled(auth()->user()->is($user))>
                                                @foreach (['admin', 'instructor', 'staff', 'student'] as $roleName)
                                                    <option value="{{ $roleName }}" @selected(($user->getRoleNames()->first() ?? $user->role?->role_name) === $roleName)>{{ ucfirst($roleName) }}</option>
                                                @endforeach
                                            </select>
                                            <button class="rounded-md bg-indigo-600 px-3 py-2 text-white disabled:opacity-50" @disabled(auth()->user()->is($user))>Save</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-5 py-4">{{ $users->links() }}</div>
            </div>
        </div>
    </div>
</x-app-layout>
