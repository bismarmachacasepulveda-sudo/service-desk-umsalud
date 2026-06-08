<!DOCTYPE html>
<html>
<head>
    <title>Informe de Actividades</title>
    <style>
        /* Ajuste de márgenes: 1.27cm es el estándar estrecho */
        body { 
            font-family: 'Times New Roman', serif; 
            font-size: 12pt; 
            line-height: 1.4; 
            margin: 1.27cm 1.27cm; 
        }
        .header { text-align: center; font-weight: bold; margin-bottom: 30px; }
        .sub-header { margin-bottom: 25px; }
        .field-row { display: table; width: 100%; margin-bottom: 8px; }
        .field-label { font-weight: bold; display: table-cell; width: 60px; vertical-align: top; }
        .field-value { display: table-cell; padding-left: 10px; }
        .content { text-align: justify; margin-top: 20px; }
        .activity-list { margin-left: 20px; margin-top: 10px; }
        .activity-item { margin-bottom: 10px; }
        
        /* Contenedor de firmas en paralelo */
        .footer-table { 
            width: 100%; 
            margin-top: 50px; 
            display: table; 
            table-layout: fixed;
        }
        .signature-box { 
            display: table-cell; 
            text-align: center; 
            vertical-align: top;
            padding: 0 10px;
        }
        .signature-line { 
            border-top: 1px solid black; 
            width: 80%; 
            margin: 0 auto; 
            margin-bottom: 8px; 
        }
        .signature-text {
            font-size: 10pt;
            font-weight: bold;
            line-height: 1.2;
        }
    </style>
</head>
<body>

    <div class="header">
        UNIVERSIDAD MAYOR DE SAN ANDRÉS<br>
        FACULTAD DE MEDICINA, ENFERMERÍA, NUTRICIÓN Y TECNOLOGÍA MÉDICA<br>
        <br>
        <span style="text-decoration: underline;">INFORME DE ACTIVIDADES</span>
    </div>

    <div class="sub-header">
        <div class="field-row">
            <span class="field-label">A:</span>
            <span class="field-value">
                {{ $nombre_decano }}<br>
                DECANO DE LA FACULTAD DE MEDICINA, ENFERMERÍA,<br>
                NUTRICIÓN Y TECNOLOGÍA MÉDICA
            </span>
        </div>

        <div class="field-row">
            <span class="field-label">VIA:</span>
            <span class="field-value">
                {{ $nombre_jefe }}<br>
                ANALISTA DE SISTEMAS - FACULTAD DE MEDICINA
            </span>
        </div>

        <div class="field-row">
            <span class="field-label">DE:</span>
            <span class="field-value">
                {{ strtoupper($usuario->name) }}<br>
                {{ strtoupper($usuario->cargo ?? 'PERSONAL DE SOPORTE') }} - RED UMSALUD
            </span>
        </div>

        <div class="field-row">
            <span class="field-label">REF:</span>
            <span class="field-value">
                INFORME DE ACTIVIDADES DEL MES DE {{ strtoupper($mes) }}<br>
                GESTIÓN {{ $gestion }}
            </span>
        </div>
    </div>

    <hr style="border: 0.5px solid black;">

    <div class="content">
        <p><strong>I. INTRODUCCIÓN</strong></p>
        <p>
            Por intermedio de la presente, me dirijo a usted con el propósito de presentarle mi informe de actividades realizadas 
            como parte del equipo de la Red UMSALUD, correspondientes al periodo del {{ $fecha_inicio }} al {{ $fecha_fin }}.
        </p>

        <p><strong>II. DESARROLLO</strong></p>
        <p>Se realizaron las siguientes tareas de soporte técnico y mantenimiento:</p>
        
        <ul class="activity-list">
            @forelse($tickets as $ticket)
                <li class="activity-item">
                    Se atendió el ticket <strong>#{{ $ticket->id }}</strong> sobre <strong>"{{ $ticket->subject }}"</strong> en el área de <strong>{{ $ticket->area->name ?? 'General' }}</strong>.
                    <br>
                    <em>Solución:</em> {{ $ticket->solution_notes ?? 'No se especificaron detalles de la resolución en el sistema.' }}
                </li>
            @empty
                <li class="activity-item" style="color: #666; font-style: italic;">
                    No se registraron atenciones finalizadas en este periodo en la plataforma.
                </li>
            @endforelse
        </ul>
    </div>

    <div class="footer-table">
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-text">
                {{ strtoupper($usuario->name) }}<br>
                C.I. {{ $usuario->ci ?? '___________' }}<br>
                {{ strtoupper($usuario->cargo ?? 'SOPORTE TÉCNICO') }}
            </div>
        </div>
        
        <div class="signature-box">
            <div class="signature-line"></div>
            <div class="signature-text">
                {{ strtoupper($nombre_jefe) }}<br>
                ANALISTA DE SISTEMAS<br>
                JEFE DE ÁREA - FACULTAD DE MEDICINA
            </div>
        </div>
    </div>

</body>
</html>