<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $asunto }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f6f8;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #333333;
        }
        .wrapper {
            max-width: 620px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 32px 40px;
            text-align: center;
        }
        .header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .header p {
            color: #a0aec0;
            margin: 6px 0 0;
            font-size: 13px;
        }
        .body {
            padding: 36px 40px;
        }
        .greeting {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a2e;
            margin-bottom: 16px;
        }
        .content {
            font-size: 15px;
            line-height: 1.7;
            color: #4a5568;
            white-space: pre-line;
        }
        .divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 32px 0;
        }
        .footer {
            background-color: #f8fafc;
            padding: 20px 40px;
            text-align: center;
            font-size: 12px;
            color: #a0aec0;
            border-top: 1px solid #e2e8f0;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Multilider</h1>
            <p>tecnoweb.space</p>
        </div>

        <div class="body">
            <p class="greeting">Estimado/a {{ $nombreDestinatario }},</p>
            <div class="content">{{ $cuerpo }}</div>
            <hr class="divider">
            <p style="font-size:13px; color:#718096; margin:0;">
                Este mensaje fue enviado por el equipo de <strong>Multilider</strong>.
                Si tiene alguna consulta, contáctenos en
                <a href="mailto:contacto@tecnoweb.space" style="color:#667eea;">contacto@tecnoweb.space</a>
            </p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Multilider · tecnoweb.space<br>
            <small>Este es un correo institucional. No responda directamente a este mensaje.</small>
        </div>
    </div>
</body>
</html>
