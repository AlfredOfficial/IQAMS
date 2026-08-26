<x-staff-layout title="Leave Requests">
    @include('leave-requests.partials.content', [
        'storeRoute' => 'staff.leave-requests.store',
        'cancelRoute' => 'staff.leave-requests.cancel',
    ])
</x-staff-layout>
