@if(auth()->user()->isInstructor())
    <x-instructor-layout title="Leave Requests">
        @include('leave-requests.partials.content')
    </x-instructor-layout>
@else
    <x-app-layout>
        <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">My Leave Requests</h2></x-slot>
        @include('leave-requests.partials.content')
    </x-app-layout>
@endif
