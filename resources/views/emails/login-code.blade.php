@php
    $logo = $message->embed(public_path('brand/spider-mark.png'));

    $copy = match ($audience) {
        'platform' => ['label' => 'Super Admin', 'eyebrow' => 'Platform administration', 'intro' => 'to the '.$appName.' administration area'],
        'client' => ['label' => 'Client portal', 'eyebrow' => 'Client portal sign-in', 'intro' => 'to your client portal at '.$where],
        default => ['label' => 'Team member', 'eyebrow' => 'Workspace sign-in', 'intro' => 'to the '.$where.' workspace'],
    };

    $details = array_values(array_filter([
        $recipientEmail !== '' ? ['Account', $recipientEmail] : null,
        ['Signing in to', $where],
        $requestedAt !== '' ? ['Requested', $requestedAt] : null,
        $ipAddress ? ['From IP address', $ipAddress] : null,
    ]));
    $digits = str_split($code);
@endphp
<x-mail.layout
    :title="$code.' is your '.$appName.' sign-in code'"
    :preheader="'Your sign-in code is '.$code.'. It is valid for '.$expiresInMinutes.' minutes and works once.'"
    :brand="$appName"
    :logo="$logo"
    :support-email="$supportEmail"
    :show-brand="false">

    <x-slot:hero>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#0f172a;background-image:linear-gradient(135deg,#0f172a 0%,#1a2342 58%,#8c0f17 100%);">
            <tr>
                <td class="m-px" style="padding:26px 40px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td valign="middle">
                                <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                    <tr>
                                        <td width="44" valign="middle" style="width:44px;"><img src="{{ $logo }}" width="44" height="44" alt="{{ $appName }}" style="display:block;width:44px;height:44px;border:0;border-radius:11px;"></td>
                                        <td style="padding-left:12px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:20px;font-weight:800;letter-spacing:-0.3px;color:#ffffff;">{{ $appName }}</td>
                                    </tr>
                                </table>
                            </td>
                            <td align="right" valign="middle" class="m-stack">
                                <span style="display:inline-block;padding:6px 13px;border:1px solid rgba(255,255,255,0.28);border-radius:999px;background-color:rgba(255,255,255,0.1);font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:11px;font-weight:800;letter-spacing:0.9px;text-transform:uppercase;color:#ffffff;">{{ $copy['label'] }}</span>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </x-slot:hero>

    <div class="m-in1" style="font-size:12px;font-weight:800;letter-spacing:1.2px;text-transform:uppercase;color:#d6121b;">{{ $copy['eyebrow'] }}</div>
    <h1 class="m-ink m-h1 m-in1" style="margin:8px 0 12px 0;font-size:28px;line-height:35px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Your verification code</h1>
    <p class="m-text m-in2" style="margin:0;font-size:15.5px;line-height:25px;color:#475569;">
        Enter this code to sign in <strong class="m-ink" style="color:#0f172a;">{{ $copy['intro'] }}</strong>.
    </p>

    {{-- The code, one tile per digit --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 0 0;">
        <tr>
            <td align="center" class="m-soft" style="background-color:#fff5f5;border:1px solid #ffd5d5;border-radius:18px;padding:22px 10px 20px 10px;">
                <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center">
                    <tr>
                        @foreach ($digits as $i => $digit)
                            <td align="center" valign="middle" class="m-digit" width="46" style="width:46px;height:58px;background-color:#ffffff;border:1px solid #ffc7c7;border-bottom:3px solid #d6121b;border-radius:12px;font-family:'SFMono-Regular',Consolas,'Courier New',monospace;font-size:30px;line-height:58px;font-weight:700;color:#b30e16;animation-delay:{{ number_format(0.3 + $i * 0.07, 2) }}s;">{{ $digit }}</td>
                            @if (! $loop->last)<td width="8" style="width:8px;font-size:0;line-height:0;">&nbsp;</td>@endif
                        @endforeach
                    </tr>
                </table>
                <div class="m-muted" style="margin-top:14px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:12.5px;line-height:19px;color:#64748b;">
                    Easier to copy: <span style="font-family:'SFMono-Regular',Consolas,'Courier New',monospace;font-size:14px;font-weight:700;letter-spacing:3px;color:#b30e16;">{{ $code }}</span>
                </div>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="m-in3" style="margin:16px 0 26px 0;">
        <tr>
            <td align="center" class="m-muted" style="font-size:13px;line-height:20px;color:#64748b;">
                Valid for <strong class="m-text" style="color:#334155;">{{ $expiresInMinutes }} minutes</strong> &nbsp;&middot;&nbsp; works <strong class="m-text" style="color:#334155;">once</strong>
            </td>
        </tr>
    </table>

    <div class="m-in3" align="center" style="margin:0 0 28px 0;">
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"><tr><td>
            <x-mail.button :href="$signInUrl">Open sign-in page</x-mail.button>
        </td></tr></table>
    </div>

    <div style="margin:0 0 24px 0;">
        <div class="m-ink" style="margin:0 0 10px 0;font-size:12px;font-weight:800;letter-spacing:0.8px;text-transform:uppercase;color:#0f172a;">Sign-in request</div>
        <x-mail.facts :items="$details" />
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td class="m-soft" style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:16px 18px;font-size:13px;line-height:21px;color:#64748b;">
                <strong class="m-ink" style="color:#0f172a;font-size:13.5px;">Keep your account safe</strong><br>
                &bull;&nbsp; Never share this code. {{ $appName }} will never ask you for it.<br>
                &bull;&nbsp; Didn't request it? Ignore this email. Nobody can sign in without the code.<br>
                &bull;&nbsp; Only enter it on {{ $signInUrl }}
            </td>
        </tr>
    </table>
</x-mail.layout>
