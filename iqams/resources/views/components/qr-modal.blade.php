{{--
    Reusable QR modal. Each page that uses this must define `qrModal` in its
    root x-data: qrModal: { show: false, value: '', label: '' }
    Open it by setting: qrModal = { show: true, value: '<the qr_code>', label: '<display name>' }
--}}
<div x-show="qrModal.show" x-cloak
     x-effect="if (qrModal.show && qrModal.value && $refs.qrTarget) { $refs.qrTarget.innerHTML = ''; new QRCode($refs.qrTarget, { text: qrModal.value, width: 200, height: 200 }); }"
     class="fixed inset-0 z-[70] flex items-center justify-center px-4"
     style="background: rgba(0,0,0,0.4);">
    <div @click.outside="qrModal.show = false" class="bg-white rounded-lg shadow-xl w-full max-w-sm p-6 text-center">
        <h3 class="text-lg font-semibold text-gray-800 mb-1" x-text="qrModal.label"></h3>
        <p class="text-xs text-gray-400 mb-4">Scan this code for attendance</p>

        <div x-ref="qrTarget" class="flex justify-center mb-3"></div>
        <div class="flex items-center justify-center gap-3">
            <button type="button"
                @click="
                    const el = $refs.qrTarget.querySelector('canvas') || $refs.qrTarget.querySelector('img');
                    const dataUrl = el.tagName === 'CANVAS' ? el.toDataURL('image/png') : el.src;
                    const w = window.open('', '_blank');
                    w.document.write('<img src=\'' + dataUrl + '\' onload=\'window.print();window.close()\'>');
                "
                class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded">
                Print
            </button>
            <button type="button" @click="qrModal.show = false" class="text-sm text-gray-500 hover:text-gray-700">
                Close
            </button>
        </div>
    </div>
</div>
