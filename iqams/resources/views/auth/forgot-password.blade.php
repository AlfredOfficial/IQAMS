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
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Email Setup or Reset Link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
