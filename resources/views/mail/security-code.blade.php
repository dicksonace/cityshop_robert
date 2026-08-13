<x-mail::message>
# Hello {{ $name }}!

Use this one-time code to **{{ $purpose }}**. It expires in **{{ $expiresMinutes }} minutes**.

<x-mail::panel>
**{{ $code }}**
</x-mail::panel>

Do not share this code with anyone. CityShop staff will never ask for it by email, SMS, or chat.

If you did not request this, you can ignore this email. Your account stays safe.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
