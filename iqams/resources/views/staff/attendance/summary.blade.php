<x-staff-layout title="Monthly Summary">
    <div class="space-y-5">
        <h2 class="text-xl font-semibold">Monthly Attendance Summary</h2>
        <form class="flex flex-wrap gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70">
            <select name="month" class="rounded-xl border-slate-300">@foreach(range(1,12) as $value)<option value="{{ $value }}" @selected($month===$value)>{{ \Carbon\Carbon::create(null,$value)->format('F') }}</option>@endforeach</select>
            <input name="year" type="number" min="2000" max="2100" value="{{ $year }}" class="rounded-xl border-slate-300">
            <button class="rounded-xl bg-indigo-600 px-5 py-2 text-white hover:bg-indigo-700">View</button>
        </form>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">@foreach(['Expected working days'=>'expectedDays','Excluded / non-working'=>'excludedDays','Present'=>'presentDays','Absent'=>'absentDays','Approved leave'=>'leaveDays','Attendance'=>'percentage','Late'=>'lateCount','Early out'=>'earlyOutCount','Incomplete'=>'incompleteCount','In Progress'=>'inProgressCount','Total hours'=>'totalMinutes'] as $label=>$key)<div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/70"><p class="text-xs uppercase text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-bold">@if($key==='percentage'){{ $totals[$key] }}%@elseif($key==='totalMinutes'){{ intdiv($totals[$key],60) }}h {{ $totals[$key]%60 }}m @else{{ $totals[$key] }}@endif</p></div>@endforeach</div>
        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70">@include('personnel.partials.attendance-table')</div>
    </div>
</x-staff-layout>
