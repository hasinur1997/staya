@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('apple-touch-icon.png') }}" class="brand-mark" width="36" height="36" alt="">
<span class="brand-name">{!! $slot !!}</span>
</a>
</td>
</tr>
