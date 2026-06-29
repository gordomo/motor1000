@php
    $tenant  = $appt->tenant;
    $logo    = $tenant?->logo_path ? asset('storage/' . $tenant->logo_path) : null;
    $primary = $tenant?->primary_color ?: '#1e3a5f';
    $marca   = $tenant?->name ?? 'Taller';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmá tu turno</title>
</head>
<body style="margin:0; background:#f4f4f5; font-family: Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:10px; overflow:hidden; border:1px solid #e5e7eb;">
                    <tr>
                        <td style="height:4px; background:{{ $primary }}; line-height:4px; font-size:0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff; padding:18px 28px; border-bottom:1px solid #e5e7eb; text-align:center;">
                            @if($logo)
                                <img src="{{ $logo }}" alt="{{ $marca }}" style="max-height:48px; max-width:200px;">
                            @else
                                <span style="color:{{ $primary }}; font-size:20px; font-weight:bold;">{{ $marca }}</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:28px;">
                            <h1 style="margin:0 0 8px; font-size:20px; color:#111827;">¡Hola {{ $appt->customer->name ?? '' }}!</h1>
                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6; color:#374151;">
                                Recibimos tu solicitud de turno. Para confirmarlo, hacé clic en el botón:
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 20px;">
                                <tr><td>
                                    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">
                                        <tr><td style="padding:14px 18px; font-size:14px; line-height:1.7; color:#374151;">
                                            <strong>Servicio:</strong> {{ $appt->title }}<br>
                                            <strong>Día y hora:</strong> {{ $appt->scheduled_at?->format('d/m/Y H:i') }} hs<br>
                                            @if($appt->tenant?->address)
                                            <strong>Dónde:</strong> {{ $appt->tenant->address }}{{ $appt->tenant->city ? ', ' . $appt->tenant->city : '' }}
                                            @endif
                                        </td></tr>
                                    </table>
                                </td></tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 8px;">
                                <tr><td style="border-radius:8px; background:{{ $primary }};">
                                    <a href="{{ $confirmUrl }}" target="_blank"
                                       style="display:inline-block; padding:14px 32px; font-size:15px; font-weight:bold; color:#ffffff; text-decoration:none;">
                                        Confirmar mi turno
                                    </a>
                                </td></tr>
                            </table>

                            <p style="margin:16px 0 0; font-size:12px; color:#9ca3af; line-height:1.6;">
                                Si no solicitaste este turno, ignorá este mensaje. El enlace vence en 3 días.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px; background:#f9fafb; border-top:1px solid #e5e7eb; font-size:12px; color:#9ca3af;">
                            {{ $appt->tenant->name ?? 'Taller' }}
                            @if($appt->tenant?->whatsapp) · WhatsApp {{ $appt->tenant->whatsapp }} @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
