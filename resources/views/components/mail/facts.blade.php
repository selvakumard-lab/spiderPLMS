@props(['items'])
{{-- Label and value rows, for example the dates of a project. `items` is a list of [label, value]. --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="m-soft" style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;">
    @foreach ($items as $i => $item)
        <tr>
            <td class="m-muted {{ $i > 0 ? 'm-line' : '' }}" style="padding:13px 18px;{{ $i > 0 ? 'border-top:1px solid #e2e8f0;' : '' }}font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:0.6px;text-transform:uppercase;color:#64748b;width:42%;">{{ $item[0] }}</td>
            <td class="m-ink {{ $i > 0 ? 'm-line' : '' }}" style="padding:13px 18px;{{ $i > 0 ? 'border-top:1px solid #e2e8f0;' : '' }}font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:14.5px;font-weight:600;color:#0f172a;" align="right">{{ $item[1] }}</td>
        </tr>
    @endforeach
</table>
