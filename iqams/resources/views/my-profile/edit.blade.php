@php
    $roleName = $user->role?->role_name;
    $roleLabel = match ($roleName) {
        'student' => 'Student',
        'instructor' => 'Instructor',
        'staff' => 'Non-Teaching Staff',
        default => 'User',
    };
    $accent = match ($roleName) {
        'student' => ['bar' => 'from-teal-600 to-sky-600', 'avatar' => 'bg-teal-100 text-teal-700'],
        'instructor' => ['bar' => 'from-indigo-600 to-violet-600', 'avatar' => 'bg-indigo-100 text-indigo-700'],
        default => ['bar' => 'from-amber-600 to-orange-600', 'avatar' => 'bg-amber-100 text-amber-700'],
    };
    $backRoute = match ($roleName) {
        'instructor' => 'instructor.dashboard',
        'staff' => 'staff.dashboard',
        'student' => 'student.dashboard',
        default => 'dashboard',
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>My Profile - {{ config('app.name', 'IQAMS') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50 min-h-screen">

    <header class="bg-white border-b border-gray-200">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ route($backRoute) }}" class="text-sm text-gray-500 hover:text-gray-800 flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Dashboard
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-500 hover:text-gray-800">Log Out</button>
            </form>
        </div>
    </header>

    <div class="py-10">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">

            <h2 class="text-xl font-semibold leading-tight text-gray-800 mb-1">My Profile</h2>
            <p class="mt-1 text-sm text-gray-500 mb-6">Manage your personal information and account security.</p>

            <form method="POST" action="{{ route('my-profile.update') }}" enctype="multipart/form-data" class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-200">
                @csrf
                @method('PATCH')

                <div class="h-2 bg-gradient-to-r {{ $accent['bar'] }}"></div>

                <div class="border-b border-gray-100 p-6 sm:p-8">
                    <div class="flex flex-col gap-6 sm:flex-row sm:items-center">
                        <div
                            x-data="{ preview: @js($user->avatar_url), objectUrl: null }"
                            class="flex items-center gap-5"
                        >
                            <div class="relative h-24 w-24 shrink-0 overflow-hidden rounded-full ring-4 ring-white shadow-md">
                                <img x-show="preview" :src="preview" alt="Profile photo preview" class="h-full w-full object-cover">
                                <div x-show="!preview" class="flex h-full w-full items-center justify-center text-3xl font-semibold {{ $accent['avatar'] }}">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                            </div>

                            <div>
                                <label for="avatar" class="inline-flex cursor-pointer items-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-gray-700 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2">
                                    Change photo
                                </label>
                                <input
                                    id="avatar"
                                    name="avatar"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="sr-only"
                                    @change="if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null; preview = objectUrl || @js($user->avatar_url)"
                                >
                                <p class="mt-2 text-xs text-gray-500">JPG, PNG, or WebP. Maximum 2 MB.</p>
                                @error('avatar')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="sm:ml-auto sm:text-right">
                            <p class="text-lg font-semibold text-gray-900">{{ $user->name }}</p>
                            <p class="text-sm text-gray-500">{{ $roleLabel }}</p>
                            <div class="mt-2 flex items-center gap-2 sm:justify-end">
                                <span class="text-xs font-medium text-gray-500">Account Status</span>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->isAccountActive() ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ ucfirst($user->status) }}</span>
                            </div>
                            @unless ($user->isAccountActive())
                                <p class="mt-2 text-sm text-red-600">Attendance is disabled. Please contact the administrator.</p>
                            @endunless
                        </div>
                    </div>
                </div>

                <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-2">
                    <section>
                        <h3 class="text-base font-semibold text-gray-900">Personal information</h3>
                        <p class="mt-1 text-sm text-gray-500">Update the name and email attached to your account.</p>

                        <div class="mt-6 space-y-5">
                            <div>
                                <x-input-label for="name" value="Name" />
                                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autocomplete="name" />
                                <x-input-error class="mt-2" :messages="$errors->get('name')" />
                            </div>

                            <div>
                                <x-input-label for="email" value="Email" />
                                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="email" />
                                <x-input-error class="mt-2" :messages="$errors->get('email')" />
                            </div>

                            @if ($roleName === 'student')
                                <div class="grid gap-4 sm:grid-cols-3">
                                    <div>
                                        <x-input-label value="Student No." />
                                        <p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $user->student?->student_no ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <x-input-label value="Course" />
                                        <p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $user->student?->course?->course_code ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <x-input-label value="Section" />
                                        <p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $user->student?->section?->section_name ?? '—' }}</p>
                                    </div>
                                </div>
                            @elseif ($roleName === 'instructor')
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <x-input-label value="Employee No." />
                                        <p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $user->instructor?->employee_no ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <x-input-label value="Department" />
                                        <p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $user->instructor?->department?->department_name ?? '—' }}</p>
                                    </div>
                                </div>
                            @elseif ($roleName === 'staff')
                                <div>
                                    <x-input-label value="Employee No." />
                                    <p class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-700">{{ $user->nonTeachingStaff?->employee_no ?? '—' }}</p>
                                </div>
                            @endif
                        </div>
                    </section>

                    <section>
                        <h3 class="text-base font-semibold text-gray-900">Change password</h3>
                        <p class="mt-1 text-sm text-gray-500">Leave these fields blank to keep your current password.</p>

                        <div class="mt-6 space-y-5">
                            <div>
                                <x-input-label for="current_password" value="Current password" />
                                <x-text-input id="current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" />
                                <x-input-error class="mt-2" :messages="$errors->get('current_password')" />
                            </div>

                            <div>
                                <x-input-label for="password" value="New password" />
                                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                                <x-input-error class="mt-2" :messages="$errors->get('password')" />
                            </div>

                            <div>
                                <x-input-label for="password_confirmation" value="Confirm new password" />
                                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                            </div>
                        </div>
                    </section>
                </div>

                <div class="flex justify-end border-t border-gray-100 bg-gray-50 px-6 py-4 sm:px-8">
                    <x-primary-button>Save changes</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
