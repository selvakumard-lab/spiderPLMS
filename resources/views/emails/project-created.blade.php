@php($logo = $message->embed(public_path('brand/spider-mark.png')))
<x-mail.layout
    :title="'New project started: '.$projectName"
    :preheader="$projectName.' has started. Expected delivery: '.$dueDate.'.'"
    :brand="$workspaceName"
    :logo="$logo"
    :app-name="$appName"
    :support-email="$supportEmail">

    <div class="m-in1" style="font-size:12px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:#d6121b;">New project &middot; {{ $projectCode }}</div>
    <h1 class="m-ink m-h1 m-in1" style="margin:8px 0 12px 0;font-size:27px;line-height:34px;font-weight:800;letter-spacing:-0.4px;color:#0f172a;">{{ $projectName }}</h1>
    <p class="m-text m-in2" style="margin:0 0 24px 0;font-size:15.5px;line-height:25px;color:#475569;">
        Hello {{ $clientName }}, we have set up your project and planned the work from kickoff to delivery. Here is the plan at a glance.
    </p>

    <div class="m-in2" style="margin:0 0 26px 0;">
        <x-mail.facts :items="array_values(array_filter([
            ['Project', $projectName],
            ['Starts', $startDate],
            ['Expected delivery', $dueDate],
            $workingDays ? ['Planned effort', $workingDays.' working days'] : null,
            ['Steps planned', (string) $stepCount],
        ]))" />
    </div>

    @if (count($steps) > 0)
        <div class="m-in3" style="margin:0 0 28px 0;">
            <div class="m-ink" style="margin:0 0 14px 0;font-size:13px;font-weight:800;letter-spacing:0.6px;text-transform:uppercase;color:#0f172a;">The plan</div>
            <x-mail.steps :items="array_map(fn ($step) => [$step['title'], $step['due'] ? 'Due '.$step['due'] : null], $steps)" />
            @if ($stepCount > count($steps))
                <p class="m-muted" style="margin:14px 0 0 0;font-size:13px;color:#64748b;">and {{ $stepCount - count($steps) }} more {{ $stepCount - count($steps) === 1 ? 'step' : 'steps' }}.</p>
            @endif
        </div>
    @endif

    @if ($portalUrl)
        <div class="m-in3" style="margin:0 0 28px 0;">
            <x-mail.button :href="$portalUrl">Follow it in your portal</x-mail.button>
        </div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="m-line m-text" style="border-top:1px solid #e2e8f0;padding-top:20px;font-size:14px;line-height:23px;color:#475569;">
                Dates are our current plan. If anything we need from you is late, or the scope changes, we will tell you how it affects the delivery date.<br><br>
                <strong class="m-ink" style="color:#0f172a;">{{ $senderName }}</strong>
            </td>
        </tr>
    </table>
</x-mail.layout>
