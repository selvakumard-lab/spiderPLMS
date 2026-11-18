@props([
    'title',
    'preheader' => '',
    'brand',
    'logo' => null,
    'appName' => null,
    'supportEmail' => null,
    'tone' => 'brand',
    'footerNote' => null,
    'showBrand' => true,
    'hero' => null,
])
@php
    $accent = $tone === 'success' ? '#0f9d6b' : '#d6121b';
    $accentDark = $tone === 'success' ? '#0b7a53' : '#b30e16';
    $bar = $tone === 'success' ? 'linear-gradient(90deg,#0f9d6b,#34d399,#0f9d6b)' : 'linear-gradient(90deg,#d6121b,#ff6b6b,#d6121b)';
@endphp
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $title }}</title>
    <style>
        /* Progressive enhancement: Apple Mail, iOS Mail and some other apps play these. Gmail and Outlook show the same design, still. */
        @keyframes mfadeup { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes mfade { from { opacity: 0; } to { opacity: 1; } }
        @keyframes mpan { 0% { background-position: 0% 50%; } 100% { background-position: 200% 50%; } }
        @keyframes mshine { 0%, 55% { background-position: -180% 0; } 100% { background-position: 180% 0; } }
        @keyframes mpop { 0% { opacity: 0; transform: scale(0.5); } 60% { transform: scale(1.12); } 100% { opacity: 1; transform: scale(1); } }
        @keyframes mdigit { from { opacity: 0; transform: translateY(10px) scale(0.88); } to { opacity: 1; transform: translateY(0) scale(1); } }
        @keyframes mring { 0% { box-shadow: 0 0 0 0 rgba(15,157,107,0.35); } 100% { box-shadow: 0 0 0 16px rgba(15,157,107,0); } }
        .m-card { animation: mfadeup .8s cubic-bezier(.22,1,.36,1) both; }
        .m-brand { animation: mfade .9s ease both; }
        .m-in1 { animation: mfadeup .8s .15s cubic-bezier(.22,1,.36,1) both; }
        .m-in2 { animation: mfadeup .8s .3s cubic-bezier(.22,1,.36,1) both; }
        .m-in3 { animation: mfadeup .8s .45s cubic-bezier(.22,1,.36,1) both; }
        .m-bar { background-size: 200% 100%; animation: mpan 7s linear infinite; }
        .m-btn { background-image: linear-gradient(110deg, rgba(255,255,255,0) 35%, rgba(255,255,255,0.28) 50%, rgba(255,255,255,0) 65%); background-size: 220% 100%; background-repeat: no-repeat; animation: mshine 4.2s ease-in-out 1.4s infinite; }
        .m-digit { animation: mdigit .55s cubic-bezier(.22,1,.36,1) both; }
        .m-pop { animation: mpop .7s .25s cubic-bezier(.22,1,.36,1) both, mring 2.8s 1s ease-out infinite; }
        @media (prefers-reduced-motion: reduce) { .m-card, .m-brand, .m-in1, .m-in2, .m-in3, .m-bar, .m-btn, .m-pop, .m-digit { animation: none !important; } }
        @media only screen and (max-width: 600px) {
            .m-px { padding-left: 22px !important; padding-right: 22px !important; }
            .m-h1 { font-size: 23px !important; line-height: 30px !important; }
            .m-stack { display: block !important; width: 100% !important; }
        }
        @media (prefers-color-scheme: dark) {
            .m-page { background-color: #0b1020 !important; }
            .m-card-bg { background-color: #151b2e !important; border-color: #26304a !important; }
            .m-ink { color: #f1f5f9 !important; }
            .m-text { color: #cbd5e1 !important; }
            .m-muted { color: #94a3b8 !important; }
            .m-soft { background-color: #1b2340 !important; border-color: #2b3658 !important; }
            .m-line { border-color: #2b3658 !important; }
        }
    </style>
</head>
<body class="m-page" style="margin:0;padding:0;background-color:#f1f3f9;-webkit-text-size-adjust:100%;">
    {{-- Preview text shown in the inbox list --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#f1f3f9;font-size:1px;line-height:1px;">
        {{ $preheader }}
        &#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="m-page" style="background-color:#f1f3f9;">
        <tr>
            <td align="center" style="padding:36px 16px 40px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">

                    {{-- Brand --}}
                    @if ($showBrand)
                    <tr>
                        <td align="center" class="m-brand" style="padding:0 0 22px 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    @if ($logo)
                                        <td width="38" valign="middle" style="width:38px;"><img src="{{ $logo }}" width="38" height="38" alt="" style="display:block;width:38px;height:38px;border:0;border-radius:9px;"></td>
                                    @endif
                                    <td style="padding-left:{{ $logo ? '11px' : '0' }};font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:19px;font-weight:800;letter-spacing:-0.3px;color:#0f172a;" class="m-ink">{{ $brand }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    @endif

                    {{-- Card --}}
                    <tr>
                        <td class="m-card m-card-bg" style="background-color:#ffffff;border-radius:18px;border:1px solid #e2e8f0;overflow:hidden;box-shadow:0 12px 32px -18px rgba(15,23,42,0.28);">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr><td class="m-bar" height="5" style="height:5px;line-height:5px;font-size:0;background-color:{{ $accent }};background-image:{{ $bar }};">&nbsp;</td></tr>
                                @if ($hero)
                                    <tr><td style="padding:0;">{{ $hero }}</td></tr>
                                @endif
                                <tr>
                                    <td class="m-px" style="padding:36px 40px 34px 40px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;">
                                        {{ $slot }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" class="m-muted" style="padding:26px 20px 0 20px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;line-height:19px;color:#94a3b8;">
                            @if ($footerNote)
                                {{ $footerNote }}<br>
                            @endif
                            @if ($supportEmail)
                                Questions? Write to <a href="mailto:{{ $supportEmail }}" style="color:{{ $accent }};text-decoration:none;">{{ $supportEmail }}</a><br>
                            @endif
                            @if ($appName && $appName !== $brand)
                                Sent on behalf of {{ $brand }} through {{ $appName }}.<br>
                            @endif
                            &copy; {{ date('Y') }} {{ $appName ?? $brand }}. All rights reserved.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
