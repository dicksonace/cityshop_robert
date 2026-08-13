@props(['url'])
@php
    $logoPath = public_path('images/branding/cityshop-mark.png');
    $logoSrc = rtrim((string) config('app.url'), '/').'/images/branding/cityshop-mark.png';
    if (isset($message) && is_object($message) && method_exists($message, 'embed') && is_file($logoPath)) {
        try {
            $logoSrc = $message->embed($logoPath);
        } catch (\Throwable) {
            // Keep the public HTTPS fallback so the logo still renders.
        }
    }
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display:inline-block;text-decoration:none;text-align:center;">
<img src="{{ $logoSrc }}" width="56" height="56" alt="CityShop" class="logo" style="display:block;margin:0 auto 8px;border:0;outline:none;text-decoration:none;">
<span class="brand-name">CityShop</span>
</a>
</td>
</tr>
