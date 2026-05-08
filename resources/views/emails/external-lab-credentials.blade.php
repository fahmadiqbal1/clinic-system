<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Portal Login — {{ config('app.name', 'Aviva HealthCare') }}</title>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#f0fdf4; font-family:'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f0fdf4; padding:48px 16px;">
    <tr><td align="center">
        <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px; width:100%; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.06);">

            <tr><td style="background:#0d9488; padding:32px 36px; text-align:center;">
                <h1 style="margin:0; font-size:24px; font-weight:700; color:#ffffff;">{{ config('app.name', 'Aviva HealthCare') }}</h1>
                <p style="margin:6px 0 0; font-size:13px; color:rgba(255,255,255,0.8);">Lab Partner Portal</p>
            </td></tr>

            <tr><td style="padding:36px 36px 0;">
                <h2 style="margin:0 0 8px; font-size:20px; font-weight:700; color:#111827;">Welcome, {{ $lab->contact_name ?: $lab->name }}!</h2>
                <p style="margin:0; font-size:15px; color:#6b7280; line-height:1.6;">
                    {{ $lab->name }} has been registered as a partner lab. Use the credentials below to log in and manage your MOU documents and pricing.
                </p>
            </td></tr>

            <tr><td style="padding:24px 36px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
                    <tr><td style="padding:24px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
                            <tr><td style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:#9ca3af; padding-bottom:4px;">Email</td></tr>
                            <tr><td style="font-size:16px; font-weight:600; color:#111827;">{{ $lab->contact_email }}</td></tr>
                        </table>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
                            <tr><td style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:#9ca3af; padding-bottom:4px;">Password</td></tr>
                            <tr><td>
                                <span style="display:inline-block; background:#111827; color:#34d399; font-family:Consolas,'Courier New',monospace; font-size:15px; font-weight:600; padding:8px 16px; border-radius:6px; letter-spacing:0.5px;">{{ $plainPassword }}</span>
                            </td></tr>
                        </table>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr><td style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:#9ca3af; padding-bottom:4px;">Portal Access</td></tr>
                            <tr><td style="font-size:13px; color:#374151;">View your MOU · Upload price lists · Track referrals</td></tr>
                        </table>
                    </td></tr>
                </table>
            </td></tr>

            <tr><td style="padding:0 36px 8px;" align="center">
                <a href="{{ url('/login') }}" style="display:inline-block; background:#0d9488; color:#ffffff; font-size:15px; font-weight:600; padding:13px 36px; border-radius:8px; text-decoration:none;">Log in to your portal &rarr;</a>
            </td></tr>
            <tr><td align="center" style="padding:0 36px 28px;">
                <p style="margin:0; font-size:12px; color:#9ca3af;">{{ url('/login') }}</p>
            </td></tr>

            <tr><td style="padding:0 36px 32px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#fffbeb; border:1px solid #fde68a; border-left:4px solid #f59e0b; border-radius:6px;">
                    <tr><td style="padding:14px 18px; font-size:13px; color:#92400e; line-height:1.5;">
                        <strong>Security Notice:</strong> Change your password after your first login. Do not share your credentials.
                    </td></tr>
                </table>
            </td></tr>

            <tr><td style="background:#f9fafb; border-top:1px solid #e5e7eb; padding:20px 36px; text-align:center;">
                <p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.5;">
                    &copy; {{ date('Y') }} {{ config('app.name', 'Aviva HealthCare') }}. All rights reserved.<br>
                    This is an automated message — please do not reply.
                </p>
            </td></tr>

        </table>
    </td></tr>
</table>
</body>
</html>
