@props(['href', 'tone' => 'brand'])
@php
    $color = $tone === 'success' ? '#0f9d6b' : '#d6121b';
@endphp
<table role="presentation" cellpadding="0" cellspacing="0" border="0">
    <tr>
        <td align="center" class="m-btn" style="background-color:{{ $color }};border-radius:12px;box-shadow:0 8px 18px -8px {{ $color }};">
            <a href="{{ $href }}" target="_blank" style="display:inline-block;padding:14px 30px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;font-weight:700;letter-spacing:0.1px;color:#ffffff;text-decoration:none;border-radius:12px;">{{ $slot }}</a>
        </td>
    </tr>
</table>
