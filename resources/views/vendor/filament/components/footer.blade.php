<script
    src="https://app.sandbox.midtrans.com/snap/snap.js"
    data-client-key="{{ config('midtrans.client_key') }}">
</script>

<script>
function triggerMidtransSnap() {
    const kodeInput = document.querySelector('input[name="kode_pemesanan"]');
    const kode = kodeInput ? kodeInput.value : null;

    if (!kode) {
        document.getElementById('midtrans-status').innerText = '⚠️ Proses pesanan dulu di Step 1!';
        return;
    }

    document.getElementById('midtrans-status').innerText = '⏳ Memuat payment gateway...';

    fetch('/midtrans/snap-token', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ kode_pemesanan: kode }),
    })
    .then(res => res.json())
    .then(data => {
        if (!data.snap_token) throw new Error('Snap token tidak diterima');

        window.snap.pay(data.snap_token, {
            onSuccess: function(result) {
                document.getElementById('midtrans-status').innerText
                    = '✅ Pembayaran berhasil via ' + result.payment_type;
            },
            onPending: function(result) {
                document.getElementById('midtrans-status').innerText
                    = '⏳ Menunggu pembayaran... cek email/app kamu.';
            },
            onError: function(result) {
                document.getElementById('midtrans-status').innerText
                    = '❌ Pembayaran gagal: ' + (result.status_message ?? '');
            },
            onClose: function() {
                document.getElementById('midtrans-status').innerText
                    = 'Popup ditutup tanpa bayar.';
            },
        });
    })
    .catch(err => {
        document.getElementById('midtrans-status').innerText = '❌ Error: ' + err.message;
    });
}
</script>