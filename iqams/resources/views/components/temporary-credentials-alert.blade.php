@props(['role' => 'account'])

@if (session('generated_username') && session('generated_password'))
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-4 text-amber-900 shadow-sm" role="alert">
        <p class="font-semibold">Temporary {{ $role }} credentials</p>
        <p class="mt-1 text-sm">Share these credentials securely. They are shown once and must be replaced on first login. No email invitation is required.</p>
        <dl class="mt-3 grid gap-1 text-sm sm:grid-cols-2">
            <div><dt class="inline font-medium">Username:</dt> <dd class="inline font-mono">{{ session('generated_username') }}</dd></div>
            <div><dt class="inline font-medium">Temporary password:</dt> <dd class="inline font-mono">{{ session('generated_password') }}</dd></div>
        </dl>
    </div>
@endif
