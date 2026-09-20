<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Setting up your account, forgot your password, or your link expired? Enter the email address registered with IQAMS to request a new link. Links expire after 60 minutes and can be used once.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required
                autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4 flex items-center">
            <x-primary-button class="w-full justify-center rounded-xl py-3.5 text-sm normal-case tracking-normal">
                {{ __('Email Setup or Reset Link') }}
            </x-primary-button>
        </div>
    </form>

    <a href="{{ route('login') }}"
        class="mt-3 flex min-h-[3.5rem] w-full items-center justify-center rounded-xl border border-gray-300 px-4 py-3.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2">
        {{ __('Back to Login') }}
    </a>
</x-guest-layout>
