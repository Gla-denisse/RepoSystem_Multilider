<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe: {{ $nombreReporte }}</title>
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
        .info-box {
            background-color: #f0f4ff;
            border-left: 4px solid #667eea;
            border-radius: 4px;
            padding: 16px 20px;
            margin: 20px 0;
        }
        .info-box p {
            margin: 0;
            font-size: 14px;
            color: #4a5568;
            line-height: 1.6;
        }
        .info-box strong {
            color: #1a1a2e;
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
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>Multilider</h1>
            <p>Informe de Gestión</p>
        </div>

        <div class="body">
            <p class="greeting">Estimado/a {{ $nombreDestinatario }},</p>

            <p style="font-size:15px; color:#4a5568; line-height:1.7;">
                Adjunto a este correo encontrará el informe <strong>{{ $nombreReporte }}</strong>
                generado desde el sistema de gestión de <strong>Multilider</strong>.
            </p>

            <div class="info-box">
                <p>
                    <strong>Reporte:</strong> {{ $nombreReporte }}<br>
                    <strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}<br>
                    <strong>Formato:</strong> PDF
                </p>
            </div>

            <p style="font-size:14px; color:#718096; line-height:1.6;">
                Por favor revise el archivo adjunto. Si tiene alguna consulta sobre los datos presentados,
                comuníquese con el área de administración.
            </p>

            <hr class="divider">

            <p style="font-size:13px; color:#718096; margin:0;">
                Este informe fue enviado desde el sistema interno de <strong>Multilider</strong>.
                Si recibió este correo por error, por favor ignórelo.
            </p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} Multilider &nbsp;·&nbsp; Sistema de Gestión<br>
            <small>Correo generado automáticamente. No responda directamente a este mensaje.</small>
        </div>
    </div>
</body>
</html>
