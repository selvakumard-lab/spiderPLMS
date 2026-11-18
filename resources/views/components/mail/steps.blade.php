@props(['items'])
{{-- A short numbered list. `items` is a list of [title, text] (text optional). --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
    @foreach ($items as $i => $item)
        <tr>
            <td width="40" valign="top" style="width:40px;padding:{{ $i > 0 ? '14px' : '0' }} 0 0 0;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td align="center" valign="middle" width="28" height="28" style="width:28px;height:28px;background-color:#fdecec;border-radius:14px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:13px;font-weight:800;color:#b30e16;line-height:28px;">{{ $i + 1 }}</td>
                </tr></table>
            </td>
            <td valign="top" style="padding:{{ $i > 0 ? '14px' : '0' }} 0 0 0;font-family:'Segoe UI',Helvetica,Arial,sans-serif;">
                <div class="m-ink" style="font-size:14.5px;line-height:22px;font-weight:700;color:#0f172a;">{{ $item[0] }}</div>
                @if (! empty($item[1]))
                    <div class="m-muted" style="font-size:13.5px;line-height:21px;color:#64748b;">{{ $item[1] }}</div>
                @endif
            </td>
        </tr>
    @endforeach
</table>
