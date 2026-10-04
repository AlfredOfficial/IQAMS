<x-guest-layout>
    <div class="relative mx-auto w-full max-w-[30rem] rounded-[22px] border border-slate-200 bg-white p-6 shadow-[0_18px_50px_rgba(15,23,42,0.08)] sm:p-8">
        <a href="{{ route('login') }}" aria-label="{{ __('Back to Login') }}" title="{{ __('Back to Login') }}"
            class="absolute right-5 top-5 inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-300 focus:ring-offset-2 sm:right-6 sm:top-6">
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8">
                <path d="M15 5 5 15M5 5l10 10" stroke-linecap="round" />
            </svg>
        </a>

        <div class="pt-7">
            <p class="text-sm font-bold tracking-[0.12em] text-teal-700">{{ __('FORGOT PASSWORD') }}</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ __('Reset your password') }}</h1>

            <p class="mt-3 max-w-md text-sm leading-6 text-slate-600">
                {{ __('Enter your email address and we’ll send you a link to set or reset your password.') }}
            </p>

            <!-- Session Status -->
            <x-auth-session-status class="mt-4" :status="session('status')" />

            <form method="POST" action="{{ route('password.email') }}" class="mt-6">
                @csrf

                <!-- Email Address -->
                <div>
                    <x-input-label for="email" class="mb-2 text-sm font-semibold text-slate-700" :value="__('Email')" />
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                            <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="3" y="5" width="18" height="14" rx="2" />
                                <path d="m3 7 9 6 9-6" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <x-text-input id="email" class="block h-12 w-full rounded-xl border-slate-200 pl-11 text-sm shadow-none placeholder:text-slate-400 focus:border-slate-400 focus:ring-slate-300" type="email" name="email" :value="old('email')" placeholder="you@example.com" required autofocus />
                    </div>
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="mt-6 flex items-center">
                    <x-primary-button class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-slate-900 text-sm normal-case tracking-normal shadow-sm transition hover:bg-slate-800 focus:bg-slate-800">
                        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="m22 2-7 20-4-9-9-4 20-7Z" stroke-linejoin="round" />
                            <path d="M22 2 11 13" stroke-linecap="round" />
                        </svg>
                        {{ __('Email Setup or Reset Link') }}
                    </x-primary-button>
                </div>
                <p class="mt-4 text-center text-xs leading-5 text-slate-500">{{ __('The link will expire in 60 minutes and can only be used once.') }}</p>
            </form>
        </div>
    </div>
</x-guest-layout>
