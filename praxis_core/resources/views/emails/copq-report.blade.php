<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>JBS Praxis Diagnostic</title>
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
                    Diagnostic protocol complete for <strong>{{ strtoupper($assessment->company_name) }}</strong>. 
                </p>
                <p style="font-size: 14px; color: #8A9BB2; line-height: 1.6; margin-bottom: 30px;">
                    Our intelligence engine has successfully mapped your operational friction, calculating the exact capital currently leaking from your enterprise architecture due to undocumented workflows and manual latency.
                </p>

                <table width="100%" style="background-color: #03050A; border-left: 3px solid #FF3366; margin-bottom: 30px;">
                    <tr>
                        <td style="padding: 20px;">
                            <div style="font-family: monospace; font-size: 10px; color: #8A9BB2; text-transform: uppercase; margin-bottom: 5px;">Calculated Annual System Leakage:</div>
                            <div style="font-family: monospace; font-size: 24px; font-weight: bold; color: #FF3366;">
                                {{ $assessment->copq_currency }} {{ number_format($assessment->calculated_leakage) }}
                            </div>
                        </td>
                    </tr>
                </table>

                <div style="text-align: center; margin-bottom: 40px;">
                    <a href="{{ route('dossier.show', ['token' => $assessment->report_token]) }}" style="display: inline-block; background-color: #00E5FF; color: #0A1128; font-family: monospace; font-size: 12px; font-weight: bold; text-decoration: none; padding: 15px 30px; letter-spacing: 1px;">
                        ACCESS SECURE DOSSIER
                    </a>
                </div>

                <p style="font-size: 12px; color: #8A9BB2; line-height: 1.6; text-align: justify; opacity: 0.8;">
                    <strong>SECURITY NOTICE:</strong> This link contains highly sensitive financial and architectural projections based on your provided baseline. Access should be restricted to authorized executive stakeholders only.
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 20px; text-align: center; background-color: #03050A; border-top: 1px solid rgba(255,255,255,0.05);">
                <div style="font-family: monospace; font-size: 10px; color: #8A9BB2;">DOC_REF: {{ $assessment->report_token }} | GENERATED: {{ date('Y-m-d') }}</div>
            </td>
        </tr>
    </table>
</body>
</html>