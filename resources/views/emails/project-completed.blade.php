@php
    $logo = $message->embed(public_path('brand/spider-mark.png'));
    $dayWord = fn (int $n) => $n.' '.($n === 1 ? 'day' : 'days');
    $result = match ($verdict) {
        'early' => 'Delivered '.$dayWord($daysDifference).' ahead of schedule',
        'late' => 'Delivered on '.$deliveredOn,
        default => 'Delivered on schedule',
    };
@endphp
<x-mail.layout
    tone="success"
    :title="'Project delivered: '.$projectName"
    :preheader="$projectName.' is complete and delivered. Thank you for working with us.'"
    :brand="$workspaceName"
    :logo="$logo"
    :app-name="$appName"
    :support-email="$supportEmail">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="padding:0 0 18px 0;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                    <td class="m-pop" align="center" valign="middle" width="64" height="64" style="width:64px;height:64px;background-color:#0f9d6b;border-radius:32px;font-family:'Segoe UI',Arial,sans-serif;font-size:32px;font-weight:700;color:#ffffff;line-height:64px;">&#10003;</td>
                </tr></table>
            </td>
        </tr>
    </table>

    <div class="m-in1" align="center" style="text-align:center;font-size:12px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:#0f9d6b;">Project delivered &middot; {{ $projectCode }}</div>
    <h1 class="m-ink m-h1 m-in1" style="margin:8px 0 12px 0;text-align:center;font-size:27px;line-height:34px;font-weight:800;letter-spacing:-0.4px;color:#0f172a;">{{ $projectName }}</h1>
    <p class="m-text m-in2" style="margin:0 0 24px 0;text-align:center;font-size:15.5px;line-height:25px;color:#475569;">
        Hello {{ $clientName }}, your project is complete. Thank you for trusting {{ $workspaceName }} with it.
    </p>

    <div class="m-in2" style="margin:0 0 26px 0;">
        <x-mail.facts :items="[
            ['Result', $result],
            ['Started', $startedOn],
            ['Planned delivery', $plannedOn],
            ['Delivered', $deliveredOn],
            ['Time taken', $dayWord($calendarDays)],
            ['Steps completed', $stepsDone.' of '.$stepsTotal],
        ]" />
    </div>

    @if ($verdict === 'late')
        <p class="m-text m-in3" style="margin:0 0 26px 0;font-size:14px;line-height:23px;color:#475569;">
            Delivery came {{ $dayWord($daysDifference) }} after the planned date of {{ $plannedOn }}. Every change and waiting period is recorded in your portal, so you can see what moved the date.
        </p>
    @endif

    @if ($portalUrl)
        <div class="m-in3" align="center" style="margin:0 0 28px 0;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"><tr><td>
                <x-mail.button :href="$portalUrl" tone="success">View the project summary</x-mail.button>
            </td></tr></table>
        </div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="m-line m-text" style="border-top:1px solid #e2e8f0;padding-top:20px;font-size:14px;line-height:23px;color:#475569;">
                If you notice anything that needs attention, reply to this email and we will take care of it.<br><br>
                <strong class="m-ink" style="color:#0f172a;">{{ $senderName }}</strong>
            </td>
        </tr>
    </table>
</x-mail.layout>
