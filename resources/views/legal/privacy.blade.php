@extends('legal.layout')

@section('content')
    <h1>Privacy Policy</h1>
    <p class="muted">Last updated: {{ $updatedAt }}</p>
    <p>
        CityUnlock (also known as CityShop), operated at
        <a href="https://cityunlock.net">cityunlock.net</a>, respects your privacy.
        This Privacy Policy explains what personal data we collect, how we use it, and your choices
        when you use our website and mobile apps (iOS and Android).
    </p>

    <h2>1. Who we are</h2>
    <p>
        CityUnlock is a Ghana marketplace platform that connects buyers and sellers and provides related
        services such as wallet payments, order management, messaging, and China / RMB transfer tools.
    </p>
    <p>
        Contact: <a href="mailto:{{ $email }}">{{ $email }}</a>
        @if(!empty($phone))
            · Phone / WhatsApp: {{ $phone }}
        @endif
    </p>

    <h2>2. Information we collect</h2>
    <ul>
        <li>Account details: name, mobile number, email, country, region, and city</li>
        <li>Delivery addresses and order history</li>
        <li>Wallet and payment information (for example MoMo references, bank details you submit for funding or withdrawals, and payment processor records)</li>
        <li>KYC documents you upload (such as Ghana Card) for wallet and compliance features</li>
        <li>Photos, videos, voice notes, and messages you upload for products, chat, livestream, or support</li>
        <li>Device and app information for security and push notifications (for example device tokens)</li>
        <li>Usage data needed to operate and secure the service (for example login times and fraud signals)</li>
    </ul>

    <h2>3. How we use your information</h2>
    <ul>
        <li>Create and manage your buyer or seller account</li>
        <li>Process orders, deliveries, refunds, and disputes</li>
        <li>Operate wallet top-ups, withdrawals, marketplace payments, and China / RMB transfer requests</li>
        <li>Verify identity where required (KYC)</li>
        <li>Send service messages (order updates, security alerts, support replies)</li>
        <li>Improve safety, prevent fraud, and maintain the platform</li>
        <li>Comply with legal and regulatory obligations</li>
    </ul>

    <h2>4. Sharing</h2>
    <p>
        We share information only as needed to run CityUnlock: with sellers for your orders; with payment
        providers (such as Paystack or Flutterwave) to process payments; with SMS and push notification
        providers; and with authorities if required by law. We do <strong>not</strong> sell your personal data.
    </p>

    <h2>5. International processing</h2>
    <p>
        Some service providers may process data outside Ghana (for example cloud hosting, payment processors,
        or push notification services). We use reputable providers and take reasonable steps to protect your data.
    </p>

    <h2>6. Security</h2>
    <p>
        We use HTTPS encryption in transit and restrict access to personal data. No method of transmission or
        storage is 100% secure. Please protect your password and payment PIN.
    </p>

    <h2>7. Data retention and deletion</h2>
    <p>
        We keep account and transaction records as needed for operations, fraud prevention, and legal requirements.
        You may request account deletion or data access by contacting
        <a href="mailto:{{ $email }}">{{ $email }}</a>.
        Some records may be retained where required by law or to resolve disputes.
    </p>

    <h2>8. Your choices</h2>
    <ul>
        <li>Update profile and address details in the app or website</li>
        <li>Contact support to request account deletion or a copy of your data</li>
        <li>Disable push notifications in your device settings</li>
    </ul>

    <h2>9. Children</h2>
    <p>
        CityUnlock is not directed at children under 13. If you believe a child provided personal data,
        contact us and we will take appropriate action.
    </p>

    <h2>10. Changes</h2>
    <p>
        We may update this Privacy Policy from time to time. The “Last updated” date at the top will change when we do.
        Continued use of CityUnlock after an update means you accept the revised policy.
    </p>

    <h2>11. Contact</h2>
    <p>
        Privacy questions: <a href="mailto:{{ $email }}">{{ $email }}</a><br>
        Website: <a href="https://cityunlock.net">https://cityunlock.net</a><br>
        Contact page: <a href="{{ url('/contact') }}">{{ url('/contact') }}</a>
    </p>
@endsection
