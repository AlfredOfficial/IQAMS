<x-guest-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Change your password</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">A new password is required before you can continue to IQAMS.</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.force-change.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        <div>
            <x-input-label for="current_password" value="Current password" />
            <x-text-input id="current_password" name="current_password" type="password" class="mt-1 block w-full" autocomplete="current-password" required autofocus />
            <x-input-error class="mt-2" :messages="$errors->forcePasswordChange->get('current_password')" />
        </div>

        <div>
            <x-input-label for="password" value="New password" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" required />
            <x-input-error class="mt-2" :messages="$errors->forcePasswordChange->get('password')" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirm new password" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" required />
            <x-input-error class="mt-2" :messages="$errors->forcePasswordChange->get('password_confirmation')" />
        </div>

        <div class="flex items-center justify-between gap-4">
            <a class="text-sm text-teal-700 underline hover:text-teal-900" href="{{ route('password.request') }}">Forgot current password?</a>
            <x-primary-button>Update password</x-primary-button>
        </div>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-right">
        @csrf
        <button type="submit" class="text-sm text-slate-500 underline hover:text-slate-700">Logout</button>
    </form>
</x-guest-layout>
