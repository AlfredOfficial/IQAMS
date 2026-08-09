<x-student-layout title="Student Profile">
    <div class="mb-6"><h2 class="font-display text-2xl font-semibold text-slate-900">My profile</h2><p class="mt-1 text-sm text-slate-500">Review your personal and academic record. Official fields are managed by the administration.</p></div>
    <div class="grid gap-6 xl:grid-cols-3">
        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
            <div class="flex flex-col items-center text-center">
                <div class="h-28 w-28 overflow-hidden rounded-full bg-teal-100 text-teal-800 ring-4 ring-teal-50">
                    @if(Auth::user()->avatar_url)<img src="{{ Auth::user()->avatar_url }}" class="h-full w-full object-cover" alt="Current profile photo">@else<div class="grid h-full place-items-center text-4xl font-semibold">{{ strtoupper(substr($student->first_name, 0, 1)) }}</div>@endif
                </div>
                <h3 class="mt-4 font-display text-lg font-semibold">{{ $student->fullName() }}</h3><p class="text-sm text-slate-500">{{ $student->student_no }}</p>
                <a href="{{ route('student.settings') }}#photo" class="mt-5 rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-800">Change photo</a>
            </div>
        </section>
        <div class="space-y-6 xl:col-span-2">
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
                <div class="flex items-start justify-between gap-4"><div><h3 class="font-display font-semibold text-slate-900">Personal information</h3><p class="mt-1 text-sm text-slate-500">Only fields marked editable can be changed by you.</p></div><span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-medium text-teal-700">Editable contact details</span></div>
                <form method="POST" action="{{ route('student.profile.contact') }}" class="mt-6 grid gap-5 sm:grid-cols-2">@csrf @method('PATCH')
                    <div class="sm:col-span-2"><label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Full name · Admin controlled</label><div class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">{{ $student->fullName() }}</div></div>
                    <div><label for="email" class="text-sm font-medium">Personal email <span class="text-teal-600">Editable</span></label><input id="email" name="email" type="email" value="{{ old('email', Auth::user()->email) }}" required class="mt-1 w-full rounded-xl border-slate-300 focus:border-teal-600 focus:ring-teal-600">@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label for="contact_number" class="text-sm font-medium">Contact number <span class="text-teal-600">Editable</span></label><input id="contact_number" name="contact_number" value="{{ old('contact_number', $student->contact_number) }}" class="mt-1 w-full rounded-xl border-slate-300 focus:border-teal-600 focus:ring-teal-600" placeholder="e.g. +63 912 345 6789">@error('contact_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div class="sm:col-span-2"><label class="text-xs font-semibold uppercase tracking-wide text-slate-400">Student ID · Admin controlled</label><div class="mt-1 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">{{ $student->student_no }}</div></div>
                    <div class="sm:col-span-2 flex justify-end"><button class="rounded-lg bg-teal-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Save contact details</button></div>
                </form>
            </section>
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/70">
                <div class="flex items-start justify-between"><div><h3 class="font-display font-semibold text-slate-900">Academic information</h3><p class="mt-1 text-sm text-slate-500">Read-only information maintained by the Admin.</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">Read only</span></div>
                @php $academic = ['Course' => $student->course?->course_code.' - '.$student->course?->course_name, 'Year level' => $student->year_level ? 'Year '.$student->year_level : 'Not assigned', 'Section' => $student->section?->section_name ?? 'Not assigned', 'Department' => $student->course?->department?->department_name ?? 'Not assigned', 'Enrollment status' => ucfirst($student->status)]; @endphp
                <dl class="mt-6 grid gap-4 sm:grid-cols-2">@foreach($academic as $label => $value)<div class="rounded-xl border border-slate-200 bg-slate-50 p-4"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt><dd class="mt-1 text-sm font-medium text-slate-700">{{ trim($value, ' -') ?: 'Not assigned' }}</dd></div>@endforeach</dl>
            </section>
        </div>
    </div>
</x-student-layout>
