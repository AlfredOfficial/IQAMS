<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold">Scanner Security</h2>
    </x-slot>
    <div class="space-y-8 p-6">
        <section class="rounded-xl bg-white p-6 shadow">
            <h3 class="text-lg font-bold">Batch issue missing QR credentials</h3>
            <p class="mt-1 text-sm text-gray-500">This queues QR issuance for active users who do not currently have an
                active credential. Existing credentials are never replaced.</p>
            <form method="POST" action="{{ route('scanner-security.qr.batch') }}" class="mt-4 grid gap-3 md:grid-cols-5">
                @csrf<select name="role" class="rounded border-gray-300">
                    <option value="">All roles</option>
                    <option value="student">Students</option>
                    <option value="instructor">Teaching</option>
                    <option value="staff">Non-teaching</option>
                </select><select name="department_id" class="rounded border-gray-300">
                    <option value="">All departments</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}">{{ $department->department_code }} —
                            {{ $department->department_name }}</option>
                    @endforeach
                </select>
                <select name="office_unit_id" class="rounded border-gray-300">
                    <option value="">All offices</option>
                    @foreach ($officeUnits as $officeUnit)
                        <option value="{{ $officeUnit->id }}">{{ $officeUnit->code }} — {{ $officeUnit->name }}</option>
                    @endforeach
                </select>
                <select name="course_id" class="rounded border-gray-300">
                    <option value="">All courses</option>
                    @foreach ($courses as $course)
                        <option value="{{ $course->id }}">{{ $course->course_code }} — {{ $course->course_name }}
                        </option>
                    @endforeach
                </select>
                <select name="section_id" class="rounded border-gray-300">
                    <option value="">All sections</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}">{{ $section->section_name }}</option>
                    @endforeach
                </select>
                <button type="submit"
                    onclick="return confirm('Queue missing QR credential issuance for the selected users?')"
                    class="rounded bg-indigo-600 px-4 py-2 text-white md:col-span-5 md:justify-self-start">Queue batch
                    issuance</button>
            </form>
        </section>
        <section class="rounded-xl bg-white p-6 shadow">
            <h3 class="text-lg font-bold">QR batch activity</h3>
            <p class="mt-1 text-sm text-gray-500">Issued, skipped, and failed totals are retained in the audit log. QR
                values are never shown here.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left">
                            <th class="py-2">Time</th>
                            <th class="py-2">Action</th>
                            <th class="py-2">Administrator</th>
                            <th class="py-2">Issued</th>
                            <th class="py-2">Skipped</th>
                            <th class="py-2">Failed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($qrBatches as $batch)
                            <tr class="border-t border-rose-100 bg-rose-50/30 align-top">
                                <td class="py-2">{{ $batch->created_at }}</td>
                                <td class="py-2">
                                    {{ $batch->action === 'qr.batch_completed' ? 'Completed' : 'Queued' }}</td>
                                <td class="py-2">{{ $batch->actor?->name ?? 'System' }}</td>
                                <td class="py-2">{{ $batch->metadata['issued'] ?? '—' }}</td>
                                <td class="py-2">{{ $batch->metadata['skipped'] ?? '—' }}</td>
                                <td class="py-2">{{ $batch->metadata['failed'] ?? '—' }}</td>
                        </tr>@empty<tr>
                                <td colspan="6" class="py-4 text-gray-500">No QR batch activity yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" x-data="{ editTerminal: null }">
            <div class="flex items-start gap-4 border-b border-slate-200 pb-5">
                <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-indigo-50 text-indigo-600">
                    <x-heroicon-o-computer-desktop class="h-6 w-6" aria-hidden="true" />
                </div>
                <div><h3 class="text-lg font-bold text-slate-900">Registered terminals</h3>
                    <p class="mt-1 text-sm text-slate-500">Manage and monitor all attendance terminals in your system.</p></div>
            </div>
            <form method="POST" action="{{ route('scanner-security.terminals.store') }}" class="flex flex-col gap-4 border-b border-slate-200 py-5 md:flex-row" style="flex-wrap:nowrap;align-items:flex-end">
                @csrf
                <label class="min-w-0 flex-1 text-sm font-semibold text-slate-700">Terminal name<input name="name" placeholder="e.g. T-D4" required class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></label>
                <label class="min-w-0 flex-1 text-sm font-semibold text-slate-700">Trusted location<input name="location" placeholder="e.g. Main Building" required class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></label>
                <button class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700" style="margin-bottom:0"><span class="text-lg leading-none">+</span> Register terminal</button>
            </form>
            <div class="mt-5 overflow-x-auto rounded-lg border border-slate-200">
                <div class="min-w-[900px] w-full text-left text-sm"><div class="grid w-full bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500" style="display:grid;grid-template-columns:minmax(140px,1.2fr) minmax(180px,1.4fr) minmax(130px,1fr) minmax(160px,1.2fr) 180px"><div class="px-5 py-3">Terminal</div><div class="px-5 py-3">Trusted location</div><div class="px-5 py-3">Status</div><div class="px-5 py-3">Last activity</div><div class="px-5 py-3 text-right">Actions</div></div>
                    <div class="divide-y divide-slate-200">
                    @forelse($terminals as $terminal)
                        <div x-data="{ editing: false }" class="text-slate-700">
                            <form method="POST" action="{{ route('scanner-security.terminals.update', $terminal) }}" class="grid w-full items-center" style="display:grid;grid-template-columns:minmax(140px,1.2fr) minmax(180px,1.4fr) minmax(130px,1fr) minmax(160px,1.2fr) 180px">@csrf @method('PATCH')
                                <div class="px-5 py-4 font-semibold text-slate-900"><span x-show="!editing">{{ $terminal->name }}</span><input x-show="editing" name="name" value="{{ $terminal->name }}" required class="w-full rounded border-slate-300 text-sm"></div>
                                <div class="px-5 py-4"><span x-show="!editing">{{ $terminal->location }}</span><input x-show="editing" name="location" value="{{ $terminal->location }}" required class="w-full rounded border-slate-300 text-sm"></div>
                                <div class="px-5 py-4"><span x-show="!editing" class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold {{ $terminal->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}"><span class="h-2 w-2 rounded-full {{ $terminal->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>{{ $terminal->is_active ? 'Active' : 'Inactive' }}</span><select x-show="editing" name="is_active" class="rounded border-slate-300 text-sm"><option value="1" @selected($terminal->is_active)>Active</option><option value="0" @selected(!$terminal->is_active)>Inactive</option></select></div>
                                <div class="px-5 py-4 text-slate-500">{{ $terminal->last_used_at?->diffForHumans() ?? 'Never' }}</div>
                                <div class="flex justify-end gap-2 px-5 py-4"><span x-show="!editing"><x-record-action-menu><button type="button" @click="editTerminal = { id: {{ $terminal->id }}, name: @js($terminal->name), location: @js($terminal->location), is_active: {{ $terminal->is_active ? 'true' : 'false' }} }">Edit</button></x-record-action-menu></span></div>
                            </form>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-slate-500">No terminals registered.</div>
                    @endforelse
                    </div>
                </div>
            </div>
            <div x-show="editTerminal" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/40 px-4" @keydown.escape.window="editTerminal = null">
                <div x-show="editTerminal" x-transition @click.outside="editTerminal = null" class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                    <div class="flex items-start justify-between gap-4"><div><h3 class="text-lg font-bold text-slate-900">Edit terminal</h3><p class="mt-1 text-sm text-slate-500">Update the terminal name, location, or status.</p></div><button type="button" @click="editTerminal = null" class="text-2xl leading-none text-slate-400 hover:text-slate-600" aria-label="Close">&times;</button></div>
                    <form method="POST" class="mt-6 space-y-4" :action="editTerminal ? '{{ url('scanner-security/terminals') }}/' + editTerminal.id : '#'">@csrf @method('PATCH')
                        <label class="block text-sm font-semibold text-slate-700">Terminal name<input name="name" x-model="editTerminal.name" required class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></label>
                        <label class="block text-sm font-semibold text-slate-700">Trusted location<input name="location" x-model="editTerminal.location" required class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></label>
                        <label class="block text-sm font-semibold text-slate-700">Status<select name="is_active" x-model="editTerminal.is_active" class="mt-2 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"><option :value="true">Active</option><option :value="false">Inactive</option></select></label>
                        <div class="flex justify-end gap-3 border-t border-slate-200 pt-5"><button type="button" @click="editTerminal = null" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save changes</button></div>
                    </form>
                </div>
            </div>
        </section>
        <section class="hidden rounded-xl bg-white p-6 shadow">
            <h3 class="text-lg font-bold">Revoke and replace a QR card</h3>
            <p class="mt-1 text-sm text-gray-500">The current random credential is revoked immediately. Download and
                issue the replacement ID card afterward.</p>
            <form method="POST" x-data="{ user: '' }"
                :action="'{{ url('scanner-security/users') }}/' + user + '/qr/regenerate'" class="mt-4 space-y-3">@csrf
                <div class="min-w-0"><x-admin-lookup-field :endpoint="route('scanner-security.users')" name="user_id" model="user"
                        placeholder="Search active users by name..." empty-label="Select user" inline /></div><div class="flex justify-end gap-2"><button type="button" @click="user = ''; $el.form.reset()"
                    class="h-10 shrink-0 self-center whitespace-nowrap rounded border border-slate-300 bg-white px-4 text-center text-sm text-slate-700 hover:bg-slate-50">Cancel</button><button
                    :disabled="!user"
                    onclick="return confirm('Revoke this user’s current QR and issue a replacement?')"
                    style="height: 2.5rem" class="shrink-0 self-center whitespace-nowrap rounded bg-red-600 px-4 text-sm text-white disabled:opacity-50">Revoke and regenerate</button>
                </div>
            </form>
        </section>
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 pb-5"><div class="flex items-start gap-3"><div class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-indigo-50 text-indigo-600"><x-heroicon-o-shield-check class="h-5 w-5" aria-hidden="true" /></div><div><h3 class="text-lg font-bold text-slate-900">Security Flags</h3><p class="mt-1 text-sm text-slate-500">View detected security events and manage scanner security status.</p></div></div><span class="inline-flex shrink-0 items-center gap-2 rounded-full bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600"><span class="h-2 w-2 rounded-full bg-rose-500"></span>{{ $flags->where('status', 'open')->count() }} active flag{{ $flags->where('status', 'open')->count() === 1 ? '' : 's' }}</span></div>
            <div class="mt-4 overflow-x-auto rounded-lg border border-slate-200">
                <table class="min-w-[1120px] w-full text-sm">
                    @php($securityFlagHeaderClass = 'px-4 py-3 text-left align-middle text-xs font-semibold leading-5 tracking-normal text-slate-500')
                    <thead>
                        <tr class="bg-slate-50 text-left text-xs font-semibold text-slate-500">
                            <th class="{{ $securityFlagHeaderClass }}">Detected</th>
                            <th class="{{ $securityFlagHeaderClass }}">Severity</th>
                            <th class="{{ $securityFlagHeaderClass }}">Category</th>
                            <th class="{{ $securityFlagHeaderClass }}">Affected User</th>
                            <th class="{{ $securityFlagHeaderClass }}">Terminal</th>
                            <th class="{{ $securityFlagHeaderClass }}">Audit Reference</th>
                            <th class="{{ $securityFlagHeaderClass }}">Evidence</th>
                            <th class="{{ $securityFlagHeaderClass }}">Status</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs">
                        @forelse($flags as $flag)
                            <tr class="border-t">
                                <td class="px-4 py-4">{{ $flag->detected_at }}</td>
                                <td>{{ $flag->severity }}</td>
                                <td>{{ str($flag->category)->replace('_', ' ')->title() }}</td>
                                <td>{{ $flag->user?->name ?? '—' }}</td>
                                <td>{{ $flag->scannerTerminal?->name ?? '—' }}</td>
                                <td>{{ $flag->attendance_scan_audit_id ? 'Audit #' . $flag->attendance_scan_audit_id : '—' }}
                                </td>
                                <td>{{ $flag->evidence }}</td>
                                <td>
                                    <form method="POST" action="{{ route('scanner-security.flags.update', $flag) }}">
                                        @csrf @method('PATCH')<select name="status" onchange="this.form.submit()" class="rounded-full border-0 bg-rose-50 py-1 pl-3 pr-8 text-xs font-semibold text-rose-600 shadow-none focus:ring-2 focus:ring-rose-200">
                                            @foreach (['open', 'reviewed', 'confirmed', 'dismissed'] as $s)
                                                <option @selected($flag->status === $s)>{{ $s }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                        </tr>@empty<tr>
                                <td colspan="8" class="py-5 text-gray-500">No security flags.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>{{ $flags->links() }}
        </section>
        <section class="rounded-xl bg-white p-6 shadow">
            <h3 class="text-lg font-bold">Immutable scan audit trail</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Outcome</th>
                            <th>User</th>
                            <th>Admin</th>
                            <th>Terminal</th>
                            <th>Location</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($audits as $audit)
                            <tr class="border-t">
                                <td>{{ $audit->created_at }}</td>
                                <td>{{ $audit->outcome }}</td>
                                <td>{{ $audit->user?->name ?? '—' }}</td>
                                <td>{{ $audit->admin?->name ?? '—' }}</td>
                                <td>{{ $audit->scannerTerminal?->name ?? '—' }}</td>
                                <td>{{ $audit->location ?? '—' }}</td>
                                <td>{{ $audit->ip_address ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>{{ $audits->links() }}
        </section>
    </div>
</x-app-layout>
