<x-instructor-layout title="Profile">
    <div class="mb-6">
        <h2 class="text-xl font-semibold">My Profile</h2>
        <p class="text-sm text-slate-500">Manage your profile photo and account security.</p>
    </div>
    <section x-data="instructorIdCard" data-id-card-url="{{ route('id-card.show') }}"
        class="mb-6 flex flex-col items-center">
        <div class="relative w-full max-w-xs overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-lg shadow-slate-900/10">
            <div class="h-2 bg-[#10294b]"></div>
            <div aria-hidden="true"
                class="pointer-events-none absolute inset-0 grid place-items-center overflow-hidden text-[78px] font-black tracking-tighter text-[#10294b]/[0.045]">
                IQAMS
            </div>
            <div class="relative px-6 pb-6 pt-5 text-center">
                <div class="flex items-center justify-center gap-2 text-[#10294b]">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-[#10294b] text-xs font-black text-white">I</span>
                    <span class="text-sm font-extrabold tracking-[0.18em]">IQAMS</span>
                </div>
                <p class="mt-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">Instructor ID Card</p>

                <div class="mx-auto mt-5 h-24 w-24 overflow-hidden rounded-full border-4 border-white bg-blue-100 text-blue-800 shadow-md ring-1 ring-slate-200">
                    @if ($user->avatar_thumbnail_url)
                        <img loading="lazy" width="96" height="96" src="{{ $user->avatar_thumbnail_url }}"
                            class="h-full w-full object-cover" alt="{{ $user->instructor?->fullName() ?? $user->name }}">
                    @else
                        <span class="grid h-full place-items-center text-2xl font-bold">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    @endif
                </div>

                <h3 class="mt-3 truncate text-lg font-extrabold text-[#10294b]">{{ $user->instructor?->fullName() ?? $user->name }}</h3>
                <p class="mt-1 text-xs font-semibold text-slate-600">Employee ID: {{ $user->instructor?->employee_no ?? 'N/A' }}</p>
                <p class="mt-1 truncate text-xs text-slate-500">{{ $user->instructor?->department?->department_name ?? 'Department not assigned' }}</p>

                <div data-instructor-id-card-qr
                    class="mx-auto mt-5 grid min-h-40 w-fit place-items-center rounded-xl border border-slate-200 bg-white p-2 text-sm text-slate-500 shadow-sm"></div>
                <p class="mt-3 text-[10px] font-extrabold tracking-[0.16em] text-[#10294b]">SCAN FOR ATTENDANCE</p>
            </div>
        </div>
        <button type="button" @click="downloadIdCard" :disabled="downloading"
            class="mt-4 inline-flex items-center gap-2 rounded-xl bg-[#10294b] px-4 py-2 text-sm font-semibold text-white hover:bg-[#173d6d] disabled:cursor-not-allowed disabled:opacity-60">
            <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
            <span x-text="downloading ? 'Preparing ID Card...' : 'Download ID Card'"></span>
        </button>
    </section>
    <form method="POST" action="{{ route('my-profile.update') }}" enctype="multipart/form-data"
        class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">@csrf @method('PATCH')
        <div class="h-2 bg-gradient-to-r from-indigo-600 to-violet-600"></div>
        <div class="flex flex-col gap-5 border-b border-slate-100 p-6 sm:flex-row sm:items-center">
            <div x-data="{ preview: @js($user->avatar_url), objectUrl: null }" class="flex items-center gap-5">
                <div
                    class="h-24 w-24 overflow-hidden rounded-full bg-indigo-100 text-indigo-700 ring-4 ring-white shadow-md">
                    <img x-show="preview" :src="preview" class="h-full w-full object-cover"
                        alt="Profile preview">
                    <div x-show="!preview" class="grid h-full place-items-center text-3xl font-semibold">
                        {{ strtoupper(substr($user->name, 0, 1)) }}</div>
                </div>
                <div><label for="avatar"
                        class="inline-flex cursor-pointer rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Change
                        photo</label><input id="avatar" name="avatar" type="file"
                        accept="image/jpeg,image/png,image/webp" class="sr-only"
                        @change="if(objectUrl)URL.revokeObjectURL(objectUrl);objectUrl=$event.target.files[0]?URL.createObjectURL($event.target.files[0]):null;preview=objectUrl||@js($user->avatar_url)">
                    <p class="mt-2 text-xs text-slate-500">JPG, PNG, or WebP. Maximum 2 MB.</p>
                    @error('avatar')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div class="sm:ml-auto sm:text-right">
                <p class="text-lg font-semibold">{{ $user->instructor?->fullName() ?? $user->name }}</p>
                <p class="text-sm text-slate-500">Teaching Personnel</p>
                <div class="mt-2 flex items-center gap-2 sm:justify-end"><span
                        class="text-xs font-medium text-slate-500">Account Status</span><span
                        class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->isAccountActive() ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">{{ ucfirst($user->status) }}</span>
                </div>
                @unless ($user->isAccountActive())
                    <p class="mt-2 text-sm text-red-600">Attendance is disabled. Please contact the administrator.</p>
                @endunless
            </div>
        </div>
        <div class="grid gap-8 p-6 lg:grid-cols-2">
            <section>
                <h3 class="font-semibold">Personal information</h3>
                <p class="mt-1 text-sm text-slate-500">Employee information is read-only.</p>
                <div class="mt-5 space-y-4">
                    <div><x-input-label for="name" value="Account name" /><x-text-input id="name"
                            name="name" class="mt-1 block w-full" :value="old('name', $user->name)" required /><x-input-error
                            class="mt-2" :messages="$errors->get('name')" /></div>
                    <div><x-input-label for="email" value="Email" /><x-text-input id="email" name="email"
                            type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required /><x-input-error
                            class="mt-2" :messages="$errors->get('email')" /></div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div><x-input-label value="Employee ID" />
                            <p class="mt-1 rounded-xl bg-slate-50 px-3 py-2 text-sm">
                                {{ $user->instructor?->employee_no ?? '—' }}</p>
                        </div>
                        <div><x-input-label value="Department" />
                            <p class="mt-1 rounded-xl bg-slate-50 px-3 py-2 text-sm">
                                {{ $user->instructor?->department?->department_name ?? '—' }}</p>
                        </div>
                    </div>
                </div>
            </section>
            <section>
                <h3 class="font-semibold">Change password</h3>
                <p class="mt-1 text-sm text-slate-500">Leave blank to retain your current password.</p>
                <div class="mt-5 space-y-4">
                    <div><x-input-label for="current_password" value="Current password" /><x-text-input
                            id="current_password" name="current_password" type="password"
                            class="mt-1 block w-full" /><x-input-error class="mt-2" :messages="$errors->get('current_password')" /></div>
                    <div><x-input-label for="password" value="New password" /><x-text-input id="password"
                            name="password" type="password" class="mt-1 block w-full" /><x-input-error class="mt-2"
                            :messages="$errors->get('password')" /></div>
                    <div><x-input-label for="password_confirmation" value="Confirm password" /><x-text-input
                            id="password_confirmation" name="password_confirmation" type="password"
                            class="mt-1 block w-full" /></div>
                </div>
            </section>
        </div>
        <div class="flex justify-end border-t border-slate-100 bg-slate-50 px-6 py-4"><x-primary-button>Save
                changes</x-primary-button></div>
    </form>
</x-instructor-layout>
