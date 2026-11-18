<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subjectLine }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f3f9;-webkit-text-size-adjust:100%;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f1f3f9;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;">
                    <tr>
                        <td style="background-color:#ffffff;border-radius:16px;border:1px solid #e2e8f0;overflow:hidden;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr><td height="5" style="height:5px;line-height:5px;font-size:0;background-color:#d6121b;">&nbsp;</td></tr>
                                @if ($projectName)
                                    <tr>
                                        <td style="padding:28px 36px 0 36px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#d6121b;">{{ $projectName }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:{{ $projectName ? '8px' : '32px' }} 36px 0 36px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;">
                                        <h1 style="margin:0;font-size:22px;line-height:30px;font-weight:700;color:#0f172a;">{{ $subjectLine }}</h1>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:18px 36px 8px 36px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:15px;line-height:25px;color:#334155;">{!! nl2br(e($body)) !!}</td>
                                </tr>
                                <tr>
                                    <td style="padding:20px 36px 32px 36px;font-family:'Segoe UI',Helvetica,Arial,sans-serif;font-size:14px;line-height:22px;color:#475569;">
                                        <div style="border-top:1px solid #e2e8f0;padding-top:18px;">
                                            <strong style="color:#0f172a;">{{ $senderName }}</strong>
                                            @if ($signature !== '')
                                                <br>{!! nl2br(e($signature)) !!}
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
