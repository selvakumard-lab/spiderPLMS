@php($logo = $message->embed(public_path('brand/spider-mark.png')))
<x-mail.layout
    :title="$subjectLine"
    :preheader="\Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $body), 110)"
    :brand="$senderName"
    :logo="$logo">

    @if ($projectName)
        <div class="m-in1" style="font-size:12px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:#d6121b;">{{ $projectName }}</div>
    @endif
    <h1 class="m-ink m-h1 m-in1" style="margin:8px 0 18px 0;font-size:25px;line-height:32px;font-weight:800;letter-spacing:-0.3px;color:#0f172a;">{{ $subjectLine }}</h1>

    <div class="m-text m-in2" style="font-size:15.5px;line-height:26px;color:#334155;">{!! nl2br(e($body)) !!}</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="m-in3" style="margin-top:28px;">
        <tr>
            <td class="m-line m-text" style="border-top:1px solid #e2e8f0;padding-top:20px;font-size:14px;line-height:22px;color:#475569;">
                <strong class="m-ink" style="color:#0f172a;">{{ $senderName }}</strong>
                @if ($signature !== '')
                    <br>{!! nl2br(e($signature)) !!}
                @endif
            </td>
        </tr>
    </table>
</x-mail.layout>
