<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Recuperación de contraseña</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f7f7f7; color: #333; }
        .container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #ef4444; color: #fff; padding: 16px 24px; }
        .content { padding: 24px; }
        .btn { display: inline-block; background: #ef4444; color: #fff !important; padding: 12px 18px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .footer { padding: 16px 24px; font-size: 12px; color: #666; background: #fafafa; }
        p { line-height: 1.6; }
        .muted { color: #666; }
    </style>
 </head>
 <body>
    <div class="container">
        <div class="header">
            <h2>Recuperación de contraseña</h2>
        </div>
        <div class="content">
            <p>Has solicitado restablecer tu contraseña.</p>
            <p>Para continuar, haz clic en el siguiente botón y sigue las instrucciones:</p>
            <p>
                <a class="btn" href="{{ $resetUrl }}" target="_blank" rel="noopener noreferrer">Restablecer contraseña</a>
            </p>
            <p class="muted">Este enlace es válido por {{ $expiresHours }} horas. Si no has solicitado este cambio, puedes ignorar este correo.</p>
            <p class="muted">Por seguridad:</p>
            <ul class="muted">
                <li>No compartas este enlace con nadie.</li>
                <li>El enlace solo puede usarse una vez.</li>
                <li>Si expira, solicita uno nuevo desde la aplicación.</li>
            </ul>
        </div>
        <div class="footer">
            <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
            <p><a href="{{ $resetUrl }}" target="_blank" rel="noopener noreferrer">{{ $resetUrl }}</a></p>
        </div>
    </div>
 </body>
 </html>