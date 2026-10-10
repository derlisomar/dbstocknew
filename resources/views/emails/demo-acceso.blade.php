<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tu acceso a la demo de dbstock</title></head>
<body style="margin:0;padding:0;background:#f1f5f5;font-family:Arial,Helvetica,sans-serif;color:#0e1a1d;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f5;padding:28px 12px;">
<tr><td align="center">
  <table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:16px;border:1px solid #d9e4e5;">
    <tr><td style="padding:28px 32px 8px 32px;">
      <img src="{{ asset('img/landing/logo-claro.png') }}" alt="dbstock" height="44" style="height:44px;width:auto;display:block;">
    </td></tr>
    <tr><td style="padding:12px 32px 4px 32px;">
      <h1 style="margin:0 0 10px 0;font-size:24px;line-height:1.25;color:#0e1a1d;">Hola {{ $nombre }}, tu demo está lista</h1>
      <p style="margin:0 0 20px 0;font-size:16px;line-height:1.55;color:#51656b;">Ya podés probar dbstock con tu propio usuario. Estos son tus datos de ingreso:</p>
    </td></tr>
    <tr><td style="padding:0 32px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f8f8;border:1px solid #cfe6e6;border-radius:12px;">
        <tr><td style="padding:16px 20px 6px 20px;font-size:13px;color:#51656b;">Usuario</td></tr>
        <tr><td style="padding:0 20px 12px 20px;font-size:20px;font-weight:bold;font-family:'Courier New',monospace;color:#0e1a1d;">{{ $usuario }}</td></tr>
        <tr><td style="padding:4px 20px 6px 20px;font-size:13px;color:#51656b;">Clave</td></tr>
        <tr><td style="padding:0 20px 16px 20px;font-size:20px;font-weight:bold;font-family:'Courier New',monospace;color:#0e1a1d;">{{ $clave }}</td></tr>
      </table>
    </td></tr>
    <tr><td align="left" style="padding:24px 32px 8px 32px;">
      <a href="{{ $urlLogin }}" style="display:inline-block;background:#0a8487;color:#ffffff;text-decoration:none;font-weight:bold;font-size:16px;padding:14px 26px;border-radius:12px;">Entrar al sistema</a>
    </td></tr>
    <tr><td style="padding:16px 32px 4px 32px;">
      <p style="margin:0 0 10px 0;font-size:15px;line-height:1.55;color:#0e1a1d;"><strong>Para empezar:</strong></p>
      <ol style="margin:0 0 14px 18px;padding:0;font-size:15px;line-height:1.7;color:#51656b;">
        <li>Entrá a <strong>Finanzas y Cajas</strong> y abrí una caja.</li>
        <li>Andá al <strong>Punto de Venta</strong> y hacé tu primera venta.</li>
        <li>Mirá el panel, el stock y los reportes para ver cómo queda todo.</li>
      </ol>
      <p style="margin:0 0 6px 0;font-size:14px;line-height:1.55;color:#51656b;">Tu acceso funciona hasta el <strong>{{ $vence }}</strong>. Es un ambiente de prueba: no cargues datos reales de tu negocio.</p>
      <p style="margin:0 0 20px 0;font-size:14px;line-height:1.55;color:#51656b;">Si no pediste esta demo, ignorá este mensaje.</p>
    </td></tr>
    <tr><td style="padding:16px 32px 26px 32px;border-top:1px solid #e3ecec;font-size:13px;color:#7a8c91;">{{ $empresa }}</td></tr>
  </table>
</td></tr>
</table>
</body>
</html>
