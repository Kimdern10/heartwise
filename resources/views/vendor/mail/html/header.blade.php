@props(['url'])
{{--
    Logo in every email. Email apps (Gmail, Outlook…) can't show SVG and need a full
    https address, so this uses the PNG app icon from the live site (APP_URL).
--}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ rtrim(config('app.url'), '/') }}/icons/icon-192.png" class="logo" width="44" height="44" alt="">
<span class="brand-name">{{ config('app.name') }}</span>
</a>
</td>
</tr>
