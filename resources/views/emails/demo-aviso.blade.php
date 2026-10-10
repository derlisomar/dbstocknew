<!DOCTYPE html>
<html lang="es">
<body style="margin:0;padding:24px;background:#f5f8f8;font-family:Arial,Helvetica,sans-serif;color:#0e1a1d;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #d9e4e5;border-radius:16px;">
    <tr><td style="padding:28px;">
      <h1 style="margin:0 0 6px;font-size:20px;">Nuevo pedido de demo</h1>
      <p style="margin:0 0 20px;color:#51656b;font-size:14px;">Alguien pidió probar dbstock desde la página. Podés escribirle para ofrecerle ayuda.</p>
      <table role="presentation" cellspacing="0" cellpadding="0" style="font-size:15px;line-height:1.7;">
        <tr><td style="color:#51656b;padding-right:16px;">Nombre</td><td><strong>{{ $nombre }}</strong></td></tr>
        <tr><td style="color:#51656b;padding-right:16px;">Negocio</td><td><strong>{{ $negocio }}</strong></td></tr>
        <tr><td style="color:#51656b;padding-right:16px;">Correo</td><td><a href="mailto:{{ $email }}" style="color:#08696c;">{{ $email }}</a></td></tr>
        @if($telefono)<tr><td style="color:#51656b;padding-right:16px;">Teléfono</td><td>{{ $telefono }}</td></tr>@endif
        <tr><td style="color:#51656b;padding-right:16px;">Vence</td><td>{{ $vence }}</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
