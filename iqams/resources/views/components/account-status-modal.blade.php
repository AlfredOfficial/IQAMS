<div x-show="statusModal.show" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center px-4"
     style="background: rgba(0, 0, 0, 0.4);">
    <div @click.outside="statusModal.show = false" class="w-full max-w-sm rounded-lg bg-white p-6 shadow-xl">
        <h3 class="mb-2 text-lg font-semibold text-gray-800" x-text="statusModal.status === 'inactive' ? 'Deactivate Account' : 'Activate Account'"></h3>
        <p class="mb-6 text-sm text-gray-500">
            Are you sure you want to <span x-text="statusModal.status === 'inactive' ? 'deactivate' : 'activate'"></span>
            <span class="font-medium text-gray-700" x-text="statusModal.name"></span>?
            <span x-show="statusModal.status === 'inactive'">They will be unable to record attendance until the account is reactivated.</span>
        </p>

        <form method="POST" :action="'{{ url('users') }}/' + statusModal.userId + '/status'">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" :value="statusModal.status">

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="rounded px-4 py-2 text-sm font-medium text-white"
                        :class="statusModal.status === 'inactive' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'"
                        x-text="statusModal.status === 'inactive' ? 'Deactivate' : 'Activate'"></button>
                <button type="button" @click="statusModal.show = false" class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
            </div>
        </form>
    </div>
</div>
