<x-student-layout title="Account Settings">
    <div class="mb-6"><h2 class="font-sans text-2xl font-semibold text-slate-900">Account settings</h2><p class="mt-1 text-sm text-slate-500">Manage your profile photo, password, and account session.</p></div>
    <div class="grid gap-6 lg:grid-cols-2">
        <section id="photo" class="border border-slate-200 bg-white p-6" x-data="{ preview: @js(Auth::user()->avatar_url), objectUrl: null }">
            <h3 class="font-sans font-semibold">Profile photo</h3><p class="mt-1 text-sm text-slate-500">JPG, PNG, or WebP up to 2 MB.</p>
            <form method="POST" action="{{ route('student.profile.photo') }}" enctype="multipart/form-data" class="mt-6">@csrf @method('PUT')
                <div class="flex flex-col items-center gap-5 sm:flex-row">
                    <div class="h-24 w-24 shrink-0 overflow-hidden rounded-full bg-teal-100 text-teal-800"><img x-show="preview" :src="preview" class="h-full w-full object-cover" alt="Photo preview"><div x-show="!preview" class="grid h-full place-items-center text-3xl font-semibold">{{ strtoupper(substr($student->first_name, 0, 1)) }}</div></div>
                    <div><label for="avatar" class="inline-flex cursor-pointer rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Choose new photo</label><input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" required class="sr-only" @change="if(objectUrl) URL.revokeObjectURL(objectUrl); objectUrl=$event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null; preview=objectUrl || @js(Auth::user()->avatar_url)"><p class="mt-2 text-xs text-slate-400">A preview appears before you save.</p>@error('avatar')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                </div><div class="mt-6"><button class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Save photo</button></div></form>
                @if(Auth::user()->avatar_path)<form method="POST" action="{{ route('student.profile.photo.destroy') }}" class="mt-3">@csrf @method('DELETE')<button class="rounded-lg px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Remove current photo</button></form>@endif
        </section>
        <section class="border border-slate-200 bg-white p-6">
            <h3 class="font-sans font-semibold">Change password</h3><p class="mt-1 text-sm text-slate-500">Confirm your current password before choosing a new one.</p>
            <form method="POST" action="{{ route('student.password') }}" class="mt-6 space-y-4">@csrf @method('PUT')
                <div><label for="current_password" class="text-sm font-medium">Current password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required class="mt-1 w-full rounded-xl border-slate-300">@error('current_password', 'passwordUpdate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label for="password" class="text-sm font-medium">New password</label><input id="password" name="password" type="password" autocomplete="new-password" required class="mt-1 w-full rounded-xl border-slate-300">@error('password', 'passwordUpdate')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div><label for="password_confirmation" class="text-sm font-medium">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="mt-1 w-full rounded-xl border-slate-300"></div>
                <button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white">Change password</button>
            </form>
        </section>
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70 lg:col-span-2"><h3 class="font-sans font-semibold">Account session</h3><p class="mt-1 text-sm text-slate-500">Sign out safely when using a shared device.</p><form method="POST" action="{{ route('logout') }}" class="mt-5">@csrf<button class="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white">Logout</button></form></section>
    </div>
</x-student-layout>
