<form method="POST" :action="formAction" class="grid min-h-0 gap-4 overflow-y-auto px-6 py-5 md:grid-cols-2">
    @csrf
    <input type="hidden" name="form_context" value="school_event_modal">
    <input type="hidden" name="_method" value="PUT" :disabled="!editing">
    <input type="hidden" name="event_id" :value="form.id">
    @if($errors->any())<div class="rounded-md bg-red-50 p-3 text-sm text-red-700 md:col-span-2"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div><label class="text-sm font-medium text-gray-700">Title</label><input name="title" x-model="form.title" required class="mt-1 block w-full rounded-md border-gray-300"></div>
    <div><label class="text-sm font-medium text-gray-700">Location</label><input name="location" x-model="form.location" class="mt-1 block w-full rounded-md border-gray-300"></div>
    <div class="md:col-span-2"><label class="text-sm font-medium text-gray-700">Description</label><textarea name="description" x-model="form.description" rows="2" class="mt-1 block w-full rounded-md border-gray-300"></textarea></div>
    <div><label class="text-sm font-medium text-gray-700">Starts</label><input type="datetime-local" name="starts_at" x-model="form.starts_at" required class="mt-1 block w-full rounded-md border-gray-300"></div>
    <div><label class="text-sm font-medium text-gray-700">Ends</label><input type="datetime-local" name="ends_at" x-model="form.ends_at" required class="mt-1 block w-full rounded-md border-gray-300"></div>
    <div><label class="text-sm font-medium text-gray-700">Attendance behavior</label><select name="attendance_mode" x-model="form.attendance_mode" class="mt-1 block w-full rounded-md border-gray-300"><option value="cancelled">Cancel class attendance</option><option value="event_attendance">Require one event scan</option><option value="unchanged">Information only; class attendance unchanged</option></select></div>
    <div><label class="text-sm font-medium text-gray-700">Target</label><select name="target_scope" x-model="form.target_scope" class="mt-1 block w-full rounded-md border-gray-300"><option value="school">Whole school</option><option value="sections">Selected sections</option><option value="schedules">Selected schedules</option></select></div>
    <div x-show="form.target_scope === 'sections'" x-cloak class="md:col-span-2"><label class="text-sm font-medium text-gray-700">Sections</label><select name="section_ids[]" x-model="form.section_ids" multiple size="5" class="mt-1 block w-full rounded-md border-gray-300">@foreach($sections as $section)<option value="{{ $section->id }}">{{ $section->section_name }} — {{ $section->school_year }} {{ $section->semester }}</option>@endforeach</select><p class="mt-1 text-xs text-gray-500">Use Ctrl/Cmd to select more than one.</p></div>
    <div x-show="form.target_scope === 'schedules'" x-cloak class="md:col-span-2"><label class="text-sm font-medium text-gray-700">Schedules overlapping this event</label><select name="schedule_ids[]" x-model="form.schedule_ids" multiple size="5" class="mt-1 block w-full rounded-md border-gray-300">@foreach($schedules as $schedule)<option value="{{ $schedule->id }}">{{ ucfirst($schedule->day) }} {{ \Illuminate\Support\Carbon::parse($schedule->start_time)->format('g:i A') }} — {{ $schedule->subject?->subject_code }} / {{ $schedule->section?->section_name }}</option>@endforeach</select></div>
    <div class="sticky bottom-0 flex justify-end gap-3 border-t border-gray-100 bg-white pt-4 md:col-span-2">
        <button type="button" @click="closeModal()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Cancel</button>
        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700" x-text="editing ? 'Update event' : 'Save draft'"></button>
    </div>
</form>
