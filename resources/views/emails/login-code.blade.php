<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $code }} is your {{ $appName }} sign-in code</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f3f9;-webkit-text-size-adjust:100%;">
    {{-- Preview text shown in the inbox list --}}
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#f1f3f9;font-size:1px;line-height:1px;">
        Your sign-in code is {{ $code }}. It expires in {{ $expiresInMinutes }} minutes.
        &#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;
    </div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f3f9;">
        <tr>
            <td align="center" style="padding:32px 16px;">

                <table role="presentation" width="560" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:560px;">
                    {{-- Brand --}}
                    <tr>
                        <td align="center" style="padding:0 0 20px 0;">
                            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td width="36" height="36" align="center" valign="middle" style="width:36px;height:36px;background-color:#d6121b;border-radius:10px;color:#ffffff;font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:700;line-height:36px;">&Lambda;</td>
                                    <td style="padding-left:10px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:19px;font-weight:800;letter-spacing:-0.3px;color:#0f172a;">{{ $appName }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Card --}}
                    <tr>
                        <td style="background-color:#ffffff;border-radius:16px;border:1px solid #e2e8f0;overflow:hidden;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr><td height="5" style="height:5px;line-height:5px;font-size:0;background-color:#d6121b;">&nbsp;</td></tr>
                                <tr>
                                    <td style="padding:36px 36px 8px 36px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;">
                                        <h1 style="margin:0 0 10px 0;font-size:24px;line-height:32px;font-weight:700;color:#0f172a;">Your sign-in code</h1>
                                        <p style="margin:0;font-size:15px;line-height:24px;color:#475569;">
                                            Use this code to sign in to <strong style="color:#0f172a;">{{ $where }}</strong>.
                                        </p>
                                    </td>
                                </tr>

                                {{-- Code --}}
                                <tr>
                                    <td align="center" style="padding:24px 36px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;">
                                            <tr>
                                                <td align="center" style="background-color:#fff5f5;border:1px solid #ffc7c7;border-radius:14px;padding:22px 12px;">
                                                    <div style="font-family:'SFMono-Regular',Consolas,'Courier New',monospace;font-size:40px;line-height:44px;font-weight:700;letter-spacing:12px;color:#b30e16;padding-left:12px;">{{ $code }}</div>
                                                </td>
                                            </tr>
                                        </table>
                                        <p style="margin:14px 0 0 0;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:13px;line-height:20px;color:#64748b;">
                                            This code expires in <strong style="color:#334155;">{{ $expiresInMinutes }} minutes</strong> and can be used once.
                                        </p>
                                    </td>
                                </tr>

                                {{-- Button --}}
                                <tr>
                                    <td align="center" style="padding:0 36px 28px 36px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td align="center" style="background-color:#d6121b;border-radius:10px;">
                                                    <a href="{{ $signInUrl }}" target="_blank" style="display:inline-block;padding:13px 28px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">Open sign-in page</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                {{-- Security note --}}
                                <tr>
                                    <td style="padding:0 36px 36px 36px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="border-top:1px solid #e2e8f0;padding-top:20px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:13px;line-height:21px;color:#64748b;">
                                                    <strong style="color:#334155;">Didn't ask for this?</strong>
                                                    You can safely ignore this email. Nobody can sign in without the code. Never share it with anyone, and our team will never ask you for it.
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding:24px 16px 0 16px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;line-height:19px;color:#94a3b8;">
                            Need help? Write to <a href="mailto:{{ $supportEmail }}" style="color:#d6121b;text-decoration:none;">{{ $supportEmail }}</a><br>
                            &copy; {{ date('Y') }} {{ $appName }}. All rights reserved.
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>
</html>
