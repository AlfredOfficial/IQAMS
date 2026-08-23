<x-guest-layout>
    <div class="mb-8">
        <p class="text-sm font-semibold text-teal-600">WELCOME BACK</p>
        <h2 class="mt-2 text-3xl font-bold tracking-tight text-slate-900">Sign in to your account</h2>
        <p class="mt-2 text-sm leading-6 text-slate-500">Enter your credentials to access your attendance dashboard.</p>
    </div>

    <x-auth-session-status class="mb-5 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm text-emerald-700" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="user_id" :value="__('User ID')" class="mb-2 text-sm font-semibold text-slate-700" />
            <div class="relative">
                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="2" /><path stroke-linecap="round" d="M8 9h8M8 13h5M8 17h4" /></svg>
                <x-text-input id="user_id" class="block w-full rounded-xl border-slate-200 py-3 pl-11 pr-4 text-sm shadow-sm placeholder:text-slate-400 focus:border-teal-500 focus:ring-teal-500" type="text" name="user_id" :value="old('user_id')" required autofocus autocomplete="username" placeholder="Enter your student or employee ID" />
            </div>
            <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
        </div>

        <div>
            <div class="mb-2 flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')" class="text-sm font-semibold text-slate-700" />
                @if (Route::has('password.request'))
                    <a class="text-sm font-semibold text-teal-600 transition hover:text-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 rounded" href="{{ route('password.request') }}">Forgot password?</a>
                @endif
            </div>
            <div class="relative">
                <x-heroicon-o-lock-closed class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                <x-text-input id="password" class="block w-full rounded-xl border-slate-200 py-3 pl-11 pr-4 text-sm shadow-sm placeholder:text-slate-400 focus:border-teal-500 focus:ring-teal-500" type="password" name="password" required autocomplete="current-password" placeholder="Enter your password" />
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <label for="remember_me" class="inline-flex cursor-pointer items-center gap-2.5 text-sm text-slate-600">
            <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-teal-600 shadow-sm focus:ring-teal-500" name="remember">
            <span>Remember me for 30 days</span>
        </label>

        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-teal-600 px-4 py-3.5 text-sm font-semibold text-white shadow-lg shadow-teal-600/20 transition hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2">
            Login
            <x-heroicon-o-arrow-right class="h-4 w-4" />
        </button>
    </form>
</x-guest-layout>
