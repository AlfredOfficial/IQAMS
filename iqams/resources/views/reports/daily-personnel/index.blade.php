<x-app-layout>
    <x-slot name="header">
        <div class="no-print">
            <h1 class="text-2xl font-bold tracking-tight text-gray-900">Daily Personnel Attendance</h1>
            <p class="mt-1 text-sm text-gray-500">Combined daily attendance for teaching and non-teaching personnel.</p>
        </div>
    </x-slot>

    <style>
        .attendance-document { background: #fff; color: #111827; padding: 2rem; }
        .report-heading { margin-bottom: 1.25rem; text-align: center; }
        .report-heading h2 { font-size: 1.1rem; font-weight: 700; letter-spacing: .04em; }
        .report-heading h3 { margin-top: .2rem; font-size: 1rem; font-weight: 700; }
        .report-heading p { margin-top: .45rem; font-size: .875rem; }
        .attendance-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: .8rem; }
        .attendance-table th, .attendance-table td { border: 1px solid #374151; padding: .42rem .5rem; }
        .attendance-table th { background: #f3f4f6; text-align: center; vertical-align: middle; font-weight: 700; }
        .attendance-table td:not(:first-child) { text-align: center; white-space: nowrap; }
        .attendance-table .name-column { width: 36%; }
        .attendance-table .empty-report { padding: 1.5rem; text-align: center; color: #6b7280; }
        .report-signatures { display: grid; grid-template-columns: 1fr 1fr .75fr; gap: 2rem; margin-top: 2rem; font-size: .8rem; }
        .report-signatures p { display: flex; align-items: end; gap: .4rem; white-space: nowrap; }
        .report-signatures span { display: inline-block; min-width: 10rem; flex: 1; border-bottom: 1px solid #111827; }
        .report-signatures .date-line { min-width: 7rem; }

        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            html, body { height: auto !important; overflow: visible !important; background: #fff !important; }
            body > div, #app-content, main { height: auto !important; min-height: 0 !important; margin: 0 !important; overflow: visible !important; }
            aside, body > div > div.lg\:hidden, #app-content > header, .no-print, [role="status"] { display: none !important; }
            .report-shell { margin: 0 !important; padding: 0 !important; max-width: none !important; }
            .attendance-document { padding: 0; box-shadow: none !important; }
            .attendance-table { font-size: 9pt; }
            .attendance-table thead { display: table-header-group; }
            .attendance-table tr { break-inside: avoid; page-break-inside: avoid; }
            .report-signatures { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>

    <div class="report-shell mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <form method="GET" action="{{ route('admin.reports.daily-personnel.index') }}" data-report-filter class="no-print mb-6 rounded-lg bg-white p-5 shadow-sm">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label for="date" class="mb-1 block text-sm font-medium text-gray-700">Date</label>
                    <input id="date" type="date" name="date" value="{{ $date->toDateString() }}" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="personnel_type" class="mb-1 block text-sm font-medium text-gray-700">Personnel Type</label>
                    <select id="personnel_type" name="personnel_type" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Personnel</option>
                        <option value="instructor" @selected(($filters['personnel_type'] ?? '') === 'instructor')>Teaching Personnel</option>
                        <option value="staff" @selected(($filters['personnel_type'] ?? '') === 'staff')>Non-Teaching Staff</option>
                    </select>
                    @error('personnel_type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="department_id" class="mb-1 block text-sm font-medium text-gray-700">Department</label>
                    <select id="department_id" name="department_id" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Departments</option>
                        @foreach($departments as $department)<option value="{{ $department->id }}" @selected((string)($filters['department_id'] ?? '') === (string)$department->id)>{{ $department->department_code }} — {{ $department->department_name }}</option>@endforeach
                    </select>
                    @error('department_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="office_unit_id" class="mb-1 block text-sm font-medium text-gray-700">Office/Unit</label>
                    <select id="office_unit_id" name="office_unit_id" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Offices/Units</option>
                        @foreach($officeUnits as $officeUnit)<option value="{{ $officeUnit->id }}" @selected((string)($filters['office_unit_id'] ?? '') === (string)$officeUnit->id)>{{ $officeUnit->code }} — {{ $officeUnit->name }}</option>@endforeach
                    </select>
                    @error('office_unit_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="personnel_id" class="mb-1 block text-sm font-medium text-gray-700">Personnel</label>
                    <select id="personnel_id" name="personnel_id" class="w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Personnel</option>
                        @foreach($personnel as $person)<option value="{{ $person['user_id'] }}" @selected((string)($filters['personnel_id'] ?? '') === (string)$person['user_id'])>{{ $person['name'] }} ({{ $person['personnel_type'] === 'instructor' ? 'Teaching' : 'Non-Teaching' }})</option>@endforeach
                    </select>
                    @error('personnel_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Generate Report</button>
                <a href="{{ route('admin.reports.daily-personnel.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Reset</a>
                <div class="ml-auto flex flex-wrap gap-2">
                    @foreach(['pdf' => 'Export PDF', 'xlsx' => 'Export Excel'] as $format => $label)
                        <button type="button" data-report-export-format="{{ $format }}" data-report-export-url="{{ route('admin.reports.daily-personnel.exports.store') }}" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ $label }}</button>
                    @endforeach
                    <button type="button" onclick="window.print()" class="rounded border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Print</button>
                </div>
            </div>
            <div data-report-export-status class="mt-3 text-sm text-gray-600" aria-live="polite"></div>
        </form>

        <div class="overflow-hidden rounded-lg bg-white shadow-sm">
            @include('reports.daily-personnel._report')
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-report-export-format]').forEach((button) => {
            button.addEventListener('click', async () => {
                const filterForm = document.querySelector('[data-report-filter]');
                const status = document.querySelector('[data-report-export-status]');
                const body = new FormData(filterForm);
                body.set('format', button.dataset.reportExportFormat);
                button.disabled = true;
                status.textContent = 'Preparing export…';

                try {
                    const response = await fetch(button.dataset.reportExportUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body,
                    });
                    const payload = await response.json();
                    if (!response.ok) throw new Error(payload.message || 'The export could not be queued.');
                    status.textContent = 'Export queued. This page will provide the download when it is ready.';

                    const poll = async () => {
                        const result = await fetch(payload.status_url, { headers: { 'Accept': 'application/json' } });
                        const current = await result.json();
                        if (current.status === 'completed' && current.download_url) {
                            const link = document.createElement('a');
                            link.href = current.download_url;
                            link.download = '';
                            link.textContent = 'Download completed export';
                            link.className = 'ml-2 font-medium text-indigo-600 hover:text-indigo-800';
                            status.appendChild(link);
                            button.disabled = false;
                            return;
                        }
                        if (current.status === 'failed' || current.status === 'expired') {
                            status.textContent = 'The export is no longer available. Please request it again.';
                            button.disabled = false;
                            return;
                        }
                        window.setTimeout(poll, 2000);
                    };
                    window.setTimeout(poll, 1000);
                } catch (error) {
                    status.textContent = error.message || 'The export could not be queued.';
                    button.disabled = false;
                }
            });
        });
    </script>
</x-app-layout>
