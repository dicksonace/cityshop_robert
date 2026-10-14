<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pay with Mobile Money · CityShop</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f3f4f6; color: #111827; }
        .wrap { max-width: 420px; margin: 0 auto; padding: 24px 16px; }
        .card { background: #fff; border-radius: 20px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .muted { color: #6b7280; font-size: 14px; }
        .amount { font-size: 32px; font-weight: 900; margin: 6px 0 2px; }
        label { display: block; font-size: 13px; font-weight: 700; margin: 18px 0 6px; }
        input { width: 100%; padding: 13px 14px; font-size: 16px; border: 1px solid #d1d5db; border-radius: 12px; }
        .nets { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .net { padding: 12px 4px; border: 1px solid #d1d5db; border-radius: 12px; background: #fff; font-weight: 700; font-size: 14px; cursor: pointer; }
        .net.on { border-color: #ea580c; background: #fff7ed; color: #c2410c; }
        .btn { width: 100%; margin-top: 20px; padding: 14px; font-size: 16px; font-weight: 800; color: #fff; background: #ea580c; border: 0; border-radius: 12px; cursor: pointer; }
        .btn:disabled { opacity: .6; }
        .link { display: block; text-align: center; margin-top: 14px; color: #6b7280; font-size: 14px; }
        .error { color: #b91c1c; font-size: 14px; margin-top: 12px; }
        .wait { text-align: center; padding: 12px 0; }
        .spin { width: 40px; height: 40px; margin: 8px auto 16px; border: 4px solid #fed7aa; border-top-color: #ea580c; border-radius: 50%; animation: s 1s linear infinite; }
        @keyframes s { to { transform: rotate(360deg); } }
        .hidden { display: none; }
        .brand { text-align: center; font-size: 12px; color: #9ca3af; margin-top: 16px; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="muted">{{ $title }}</div>
        <div class="amount">GH₵{{ number_format((float) $payment->amount, 2) }}</div>
        <div class="muted">Pay with Mobile Money</div>

        @if ($payment->status === 'success')
            <p class="wait">This payment is already complete.</p>
        @else
            <form id="form">
                <label>Network</label>
                <div class="nets" id="nets">
                    @foreach (['mtn' => 'MTN', 'telecel' => 'Telecel', 'airteltigo' => 'AirtelTigo'] as $value => $text)
                        <button type="button" class="net {{ $network === $value ? 'on' : '' }}" data-net="{{ $value }}">{{ $text }}</button>
                    @endforeach
                </div>
                <label for="phone">Mobile Money number</label>
                <input id="phone" name="phone" type="tel" inputmode="numeric" placeholder="0240000000" value="{{ $payment->phone }}" required>
                <div class="error hidden" id="error"></div>
                <button class="btn" id="pay" type="submit">Pay GH₵{{ number_format((float) $payment->amount, 2) }}</button>
                <a class="link" href="{{ $cancelUrl }}">Cancel</a>
            </form>

            <div class="wait hidden" id="waiting">
                <div class="spin"></div>
                <strong>Check your phone</strong>
                <p class="muted">Approve the GH₵{{ number_format((float) $payment->amount, 2) }} prompt and enter your MoMo PIN. This page updates by itself.</p>
                <a class="link" href="#" id="again">Didn't get a prompt? Try again</a>
            </div>
        @endif
    </div>
    <div class="brand">Secured by Junipay · CITY UNLOCK VENTURES</div>
</div>

@if ($payment->status !== 'success')
<script>
(function () {
    var network = @json($network);
    var form = document.getElementById('form');
    var waiting = document.getElementById('waiting');
    var error = document.getElementById('error');
    var payBtn = document.getElementById('pay');
    var timer = null;
    var token = document.querySelector('meta[name="csrf-token"]').content;

    document.querySelectorAll('.net').forEach(function (btn) {
        btn.addEventListener('click', function () {
            network = btn.dataset.net;
            document.querySelectorAll('.net').forEach(function (b) { b.classList.toggle('on', b === btn); });
        });
    });

    function showError(message) {
        error.textContent = message;
        error.classList.remove('hidden');
        form.classList.remove('hidden');
        waiting.classList.add('hidden');
        payBtn.disabled = false;
        if (timer) { clearInterval(timer); timer = null; }
    }

    function handle(data) {
        if (data.redirect) { window.location.href = data.redirect; return true; }
        if (data.status === 'failed') { showError(data.message || 'The payment was declined or cancelled. Try again.'); return true; }
        return false;
    }

    function poll() {
        fetch(@json($statusUrl), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(handle)
            .catch(function () {});
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        error.classList.add('hidden');
        payBtn.disabled = true;
        fetch(@json($startUrl), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ phone: document.getElementById('phone').value, network: network })
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, data: d }; }); })
            .then(function (res) {
                if (!res.ok) { showError(res.data.message || 'Could not send the payment prompt.'); return; }
                if (handle(res.data)) return;
                form.classList.add('hidden');
                waiting.classList.remove('hidden');
                timer = setInterval(poll, 4000);
            })
            .catch(function () { showError('Network error. Check your connection and try again.'); });
    });

    document.getElementById('again').addEventListener('click', function (e) {
        e.preventDefault();
        if (timer) { clearInterval(timer); timer = null; }
        waiting.classList.add('hidden');
        form.classList.remove('hidden');
        payBtn.disabled = false;
    });
})();
</script>
@endif
</body>
</html>
