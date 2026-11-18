{{ $appName }}

Your verification code: {{ $code }}

Use it to sign in to {{ $where }}. The code expires in {{ $expiresInMinutes }} minutes and works once.

Sign-in page: {{ $signInUrl }}

@if ($recipientEmail !== '')
Account: {{ $recipientEmail }}
@endif
@if ($requestedAt !== '')
Requested: {{ $requestedAt }}
@endif
@if ($ipAddress)
From IP address: {{ $ipAddress }}
@endif

Keep your account safe: never share this code, {{ $appName }} will never ask you for it. If you did not request it, ignore this email.

Need help? {{ $supportEmail }}
