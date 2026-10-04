<x-guest-layout>
    <div class="rounded-[22px] border border-slate-200 bg-white p-5 shadow-[0_18px_50px_rgba(15,23,42,0.08)] sm:p-5">
        <div class="mb-3">
            <p class="text-sm font-bold tracking-[0.12em] text-teal-700">{{ __('RESET PASSWORD') }}</p>
            <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">{{ __('Create a new password') }}</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">{{ __('Choose a secure password for your IQAMS account. This link can be used once.') }}</p>
        </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label class="mb-1.5 text-sm font-semibold text-slate-700" for="email" :value="__('Email')" />
            <x-text-input id="email" class="block w-full rounded-xl border-slate-200 px-4 py-2.5 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300" type="email" name="email" :value="old('email', $request->email)" required
                autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label class="mb-1.5 text-sm font-semibold text-slate-700" for="password" :value="__('Password')" />
            <x-text-input id="password" class="block w-full rounded-xl border-slate-200 px-4 py-2.5 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300" type="password" name="password" required
                autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label class="mb-1.5 text-sm font-semibold text-slate-700" for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block w-full rounded-xl border-slate-200 px-4 py-2.5 text-sm shadow-sm focus:border-slate-400 focus:ring-slate-300" type="password"
                name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div>
            <x-primary-button class="flex w-full items-center justify-center rounded-xl bg-slate-900 px-4 py-3 text-sm normal-case tracking-normal shadow-lg shadow-slate-900/20 transition hover:bg-slate-800 focus:bg-slate-800">
                {{ __('Set Password') }}
            </x-primary-button>
        </div>
    </form>
    <a class="mt-4 inline-block text-sm font-semibold text-teal-700 transition hover:text-teal-900" href="{{ route('password.request') }}">{{ __('Link expired? Request another email.') }}</a>
    </div>
</x-guest-layout>
