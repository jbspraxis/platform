<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>JBS Praxis Consultation</title>
</head>
<body style="background-color: #03050A; margin: 0; padding: 40px 20px; font-family: Helvetica, Arial, sans-serif; color: #ffffff;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; background-color: #0A1128; border: 1px solid rgba(0, 229, 255, 0.2); border-top: 4px solid #00E5FF; border-collapse: collapse;">
        <tr>
            <td style="padding: 40px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05);">
                <div style="font-family: monospace; font-size: 14px; font-weight: bold; letter-spacing: 3px; color: #00E5FF; margin-bottom: 10px;">JBS PRAXIS</div>
                <div style="font-family: monospace; font-size: 10px; color: #8A9BB2; letter-spacing: 1px;">INTELLIGENCE & ARCHITECTURE DESK</div>
            </td>
        </tr>
        <tr>
            <td style="padding: 40px;">
                <p style="font-size: 14px; color: #8A9BB2; line-height: 1.6; margin-bottom: 20px;">
                    Consultation Protocol approved for <strong>{{ strtoupper($companyName) }}</strong>.
                </p>
                <p style="font-size: 14px; color: #8A9BB2; line-height: 1.6; margin-bottom: 30px;">
                    Your dedicated Lead Architect has been assigned. Please utilize the secure communication portal below to join the session at your scheduled time: <strong style="color: #ffffff;">{{ \Carbon\Carbon::parse($appointment->start_time)->format('F j, Y @ H:i') }}</strong>.
                </p>

                <div style="text-align: center; margin-bottom: 40px;">
                    <a href="{{ $meetingLink }}" style="display: inline-block; background-color: #00E5FF; color: #0A1128; font-family: monospace; font-size: 12px; font-weight: bold; text-decoration: none; padding: 15px 30px; letter-spacing: 1px;">
                        ENTER SECURE MEETING
                    </a>
                </div>

                <p style="font-size: 12px; color: #8A9BB2; line-height: 1.6; text-align: justify; opacity: 0.8;">
                    <strong>PREPARATION NOTICE:</strong> Please ensure all relevant stakeholders are present. We will review your specific operational bottlenecks and propose a direct architectural blueprint.
                </p>
            </td>
        </tr>
    </table>
</body>
</html>