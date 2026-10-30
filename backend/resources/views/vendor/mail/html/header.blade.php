@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<span class="brand-mark">{{ mb_substr(trim($slot), 0, 1) }}</span><span class="brand-name">{{ trim($slot) }}</span>
</a>
</td>
</tr>
