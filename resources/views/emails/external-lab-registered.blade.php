<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partnership Registered — {{ config('app.name', 'Aviva HealthCare') }}</title>
</head>
<body style="margin:0; padding:0; width:100%; background-color:#eff6ff; font-family:'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#eff6ff; padding:48px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px; width:100%; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,0.06);">

                    <tr>
                        <td style="background:#0d9488; padding:32px 36px; text-align:center;">
                            <h1 style="margin:0; font-size:24px; font-weight:700; color:#ffffff; letter-spacing:0.3px;">{{ config('app.name', 'Aviva HealthCare') }}</h1>
                            <p style="margin:6px 0 0; font-size:13px; color:rgba(255,255,255,0.8); letter-spacing:0.5px;">Healthcare Management System</p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:36px 36px 0;">
                            <h2 style="margin:0 0 8px; font-size:20px; font-weight:700; color:#111827;">Lab Partnership Confirmed</h2>
                            <p style="margin:0; font-size:15px; color:#6b7280; line-height:1.6;">
                                Dear {{ $lab->contact_name ?: $lab->name }}, your laboratory has been registered as a partner with {{ config('app.name', 'Aviva HealthCare') }}.
                                We look forward to working with you.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:24px 36px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
                                <tr>
                                    <td style="padding:24px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
                                            <tr><td style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:#9ca3af; padding-bottom:4px;">Lab Name</td></tr>
                                            <tr><td style="font-size:16px; font-weight:600; color:#111827;">{{ $lab->name }}</td></tr>
                                        </table>
                                        @if($lab->city)
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
                                            <tr><td style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:#9ca3af; padding-bottom:4px;">City</td></tr>
                                            <tr><td style="font-size:15px; color:#374151;">{{ $lab->city }}</td></tr>
                                        </table>
                                        @endif
                                        @if($lab->mou_commission_pct)
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr><td style="font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:1px; color:#9ca3af; padding-bottom:4px;">Agreed Commission</td></tr>
                                            <tr><td style="font-size:15px; color:#374151;">{{ $lab->mou_commission_pct }}%</td></tr>
                                        </table>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 36px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf4; border:1px solid #bbf7d0; border-left:4px solid #16a34a; border-radius:6px;">
                                <tr>
                                    <td style="padding:14px 18px; font-size:13px; color:#166534; line-height:1.5;">
                                        Our team will be in touch with next steps. If you have any questions, please contact us directly.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#f9fafb; border-top:1px solid #e5e7eb; padding:20px 36px; text-align:center;">
                            <p style="margin:0; font-size:12px; color:#9ca3af; line-height:1.5;">
                                &copy; {{ date('Y') }} {{ config('app.name', 'Aviva HealthCare') }}. All rights reserved.<br>
                                This is an automated message — please do not reply.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
