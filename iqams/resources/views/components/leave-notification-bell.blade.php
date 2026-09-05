@if (Auth::user()->isAdmin() || Auth::user()->isStaff() || Auth::user()->isInstructor())
@php
    $notificationType = \App\Notifications\LeaveRequestNotification::class;
    $notificationKey = 'iqams.leave-notifications.'.Auth::id();
    $notificationData = request()->attributes->get($notificationKey);
    if ($notificationData === null) {
        $notificationData = [
            Auth::user()->notifications()->where('type', $notificationType)->latest()->take(8)->get(),
            Auth::user()->unreadNotifications()->where('type', $notificationType)->count(),
        ];
        request()->attributes->set($notificationKey, $notificationData);
    }
    [$leaveNotifications, $unreadCount] = $notificationData;
@endphp
@php
    $leaveIndexRoute = match (true) {
        Auth::user()->isAdmin() => 'admin.leave-requests.index',
        Auth::user()->isStaff() => 'staff.leave-requests.index',
        default => 'leave-requests.index',
    };
@endphp

<div {{ $attributes->class('relative') }} x-data="{
    open: false,
    unread: {{ $unreadCount }},
    async toggle() {
        this.open = !this.open;
        if (!this.open || !this.unread) return;
        try {
            const response = await fetch(@js(route('leave-notifications.read')), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (response.ok) window.dispatchEvent(new CustomEvent('leave-notifications-read'));
        } catch (error) {
            // Retain the badge when read state could not be saved.
        }
    }
}" @keydown.escape.window="open=false" @leave-notifications-read.window="unread=0">
    <button type="button" @click="toggle" class="relative rounded-lg border border-slate-200 bg-white p-2.5 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700" aria-label="Leave notifications" :aria-expanded="open">
        <x-heroicon-o-bell class="h-5 w-5" aria-hidden="true" />
        <span x-show="unread > 0" x-cloak x-text="unread > 99 ? '99+' : unread" class="absolute -right-2 -top-2 grid min-h-5 min-w-5 place-items-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white ring-2 ring-white"></span>
    </button>

    <div x-show="open" x-cloak x-transition.origin.top.right @click.outside="open=false" class="absolute right-0 z-[80] mt-2 w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-slate-200 bg-white text-left shadow-xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3"><div><p class="text-sm font-semibold text-slate-900">Leave notifications</p><p class="text-xs text-slate-500">Recent request activity</p></div>@if($leaveNotifications->isNotEmpty())<a href="{{ route($leaveIndexRoute) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-800">View all</a>@endif</div>
        <div class="max-h-96 overflow-y-auto divide-y divide-slate-100">
            @forelse($leaveNotifications as $notification)
                @php
                    $data = $notification->data;
                    $event = $data['event'] ?? 'updated';
                    $status = $data['status'] ?? 'pending';
                    $statusClass = match($status) {'approved'=>'bg-emerald-100 text-emerald-700','rejected'=>'bg-red-100 text-red-700','cancelled'=>'bg-slate-100 text-slate-600',default=>'bg-amber-100 text-amber-700'};
                    $message = match($event) {
                        'submitted' => Auth::user()->isAdmin() ? ($data['requester_name'].' submitted a leave request.') : 'Your leave request was submitted.',
                        'approved' => 'Your leave request was approved.',
                        'rejected' => 'Your leave request was declined.',
                        'cancelled' => Auth::user()->isAdmin() ? ($data['requester_name'].' cancelled a leave request.') : 'Your leave request was cancelled.',
                        default => 'A leave request was updated.',
                    };
                @endphp
                <a href="{{ $data['url'] ?? '#' }}" class="block px-4 py-3 transition hover:bg-slate-50 {{ $notification->read_at ? '' : 'bg-blue-50/60' }}">
                    <div class="flex items-start justify-between gap-3"><p class="text-sm font-medium leading-5 text-slate-800">{{ $message }}</p><span class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-semibold capitalize {{ $statusClass }}">{{ $status }}</span></div>
                    <p class="mt-1 text-xs text-slate-500">{{ $data['leave_type'] ?? 'Leave' }} · {{ \Illuminate\Support\Carbon::parse($data['start_date'])->format('M j') }}–{{ \Illuminate\Support\Carbon::parse($data['end_date'])->format('M j, Y') }}</p>
                    @if(!empty($data['review_notes']))<p class="mt-1 truncate text-xs text-slate-500">Note: {{ $data['review_notes'] }}</p>@endif
                    <p class="mt-1 text-[11px] text-slate-400">{{ $notification->created_at->diffForHumans() }}</p>
                </a>
            @empty
                <div class="px-5 py-10 text-center"><x-heroicon-o-bell class="mx-auto h-8 w-8 text-slate-300" /><p class="mt-2 text-sm text-slate-500">No leave notifications yet.</p></div>
            @endforelse
        </div>
    </div>
</div>
@endif
