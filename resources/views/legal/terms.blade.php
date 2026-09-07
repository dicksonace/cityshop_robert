@extends('legal.layout')

@section('content')
    <h1>Terms of Service</h1>
    <p class="muted">Last updated: {{ $updatedAt }}</p>
    <p>
        By using CityUnlock / CityShop (website and mobile apps), you agree to these Terms of Service.
    </p>

    <h2>1. The service</h2>
    <p>
        CityUnlock is an online marketplace connecting buyers and sellers in Ghana and related markets.
        We provide tools for browsing, ordering, messaging, wallet funding, withdrawals, and China / RMB
        transfer request features. Sellers are responsible for their products and fulfilment unless we
        expressly state otherwise.
    </p>

    <h2>2. Accounts</h2>
    <p>
        You must provide accurate information and keep your login, payment PIN, and device secure.
        You are responsible for activity under your account. We may suspend accounts for fraud, abuse,
        or policy violations.
    </p>

    <h2>3. Orders and payments</h2>
    <p>
        Prices, availability, and delivery terms are set by sellers or as shown in the app. Payments may be
        processed through wallet balance, mobile money, card processors, or other methods we enable.
        Chargebacks, disputes, and refunds are handled according to our policies and applicable law.
    </p>

    <h2>4. China / RMB features</h2>
    <p>
        Buy RMB / Sell RMB and related transfer tools are request-based services subject to rates, limits,
        verification, and processing times shown in the app. Submission of a request does not guarantee
        completion until confirmed by CityUnlock operations.
    </p>

    <h2>5. Acceptable use</h2>
    <p>
        You may not use CityUnlock for illegal activity, fraud, harassment, spam, IP infringement, or to
        upload harmful content. We may remove content and restrict access to protect users and the platform.
    </p>

    <h2>6. Third-party content</h2>
    <p>
        Seller listings and user-generated content belong to those users. By posting content you grant
        CityUnlock a licence to host and display it to operate the marketplace.
    </p>

    <h2>7. Limitation of liability</h2>
    <p>
        To the fullest extent permitted by law, CityUnlock is not liable for indirect or consequential losses,
        or for seller fulfilment failures beyond our control. Our total liability for a claim relating to the
        service is limited to fees you paid us in the three months before the claim.
    </p>

    <h2>8. Contact</h2>
    <p>
        Questions: <a href="mailto:{{ $email }}">{{ $email }}</a><br>
        Website: <a href="https://cityunlock.net">https://cityunlock.net</a>
    </p>
@endsection
