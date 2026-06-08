<!DOCTYPE html>
<html>
<head>
    <title>Informe Estadístico Institucional</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #333; margin-bottom: 30px;}
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #003366; padding-bottom: 10px; }
        .title { font-size: 16px; font-weight: bold; text-transform: uppercase; color: #003366;}
        .subtitle { font-size: 10px; color: #666; }

        /* Cajas KPI */
        .kpi-container { width: 100%; margin-bottom: 15px; }
        .kpi-box { 
            display: inline-block; width: 23%; margin-right: 1%; 
            background-color: #f8f9fa; border: 1px solid #ddd; 
            text-align: center; padding: 10px 0; border-radius: 4px; border-top: 3px solid #003366;
        }
        .kpi-num { font-size: 18px; font-weight: bold; color: #003366; display: block; }
        .kpi-label { font-size: 9px; text-transform: uppercase; color: #7f8c8d; }

        /* Tablas */
        h3 { border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-top: 15px; font-size: 12px; color: #800000; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th { background-color: #003366; color: white; padding: 5px; text-align: left; font-size: 9px; }
        td { border-bottom: 1px solid #eee; padding: 5px; font-size: 9px; }
        .text-right { text-align: right; }
        
        /* Gráficos CSS */
        .bar-container { width: 100%; background-color: #f0f0f0; height: 12px; border-radius: 2px; }
        .bar-fill { height: 100%; border-radius: 2px; background-color: #003366; }
        .bar-fill-red { height: 100%; border-radius: 2px; background-color: #800000; }
        
        .footer { position: fixed; bottom: -10px; left: 0px; right: 0px; text-align: center; font-size: 8px; color: #aaa; border-top: 1px solid #eee; padding-top: 5px; }
    </style>
</head>
<body>

    <div class="header">
        <div class="title">RED UMSALUD - REPORTE GERENCIAL</div>
        <div class="subtitle">{{ $titulo }} | {{ $rango }}</div>
    </div>

    <div class="kpi-container">
        <div class="kpi-box"><span class="kpi-num">{{ $total }}</span><span class="kpi-label">Total Fallas</span></div>
        <div class="kpi-box"><span class="kpi-num" style="color: #27ae60;">{{ $cerrados }}</span><span class="kpi-label">Resueltos</span></div>
        <div class="kpi-box"><span class="kpi-num" style="color: #c0392b;">{{ $abiertos }}</span><span class="kpi-label">Pendientes</span></div>
        <div class="kpi-box"><span class="kpi-num">{{ $promedio }} min</span><span class="kpi-label">T. Promedio Res.</span></div>
    </div>

    <h3>A. Desempeño del Equipo Técnico</h3>
    <table>
        <thead>
            <tr>
                <th width="40%">Técnico Especialista</th>
                <th width="20%" class="text-right">Casos Asignados</th>
                <th width="20%" class="text-right">Casos Resueltos</th>
                <th width="20%" class="text-right">Tasa de Eficiencia</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tecnicos as $tech)
            @php $eficiencia = $tech->total_asignados > 0 ? round(($tech->total_resueltos / $tech->total_asignados) * 100) : 0; @endphp
            <tr>
                <td>{{ $tech->name }}</td>
                <td class="text-right">{{ $tech->total_asignados }}</td>
                <td class="text-right">{{ $tech->total_resueltos }}</td>
                <td class="text-right"><strong>{{ $eficiencia }}%</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3>B. Tiempo Medio de Reparación (MTTR) por Categoría</h3>
    <table>
        <thead>
            <tr>
                <th width="30%">Categoría de Incidencia</th>
                <th width="50%">Proporción de Tiempo</th>
                <th width="20%" class="text-right">Promedio</th>
            </tr>
        </thead>
        <tbody>
            @foreach($mttr as $m)
            @php $ancho = ($max_mttr > 0) ? ($m->tiempo_promedio / $max_mttr) * 100 : 0; @endphp
            <tr>
                <td>{{ $m->category->name }}</td>
                <td>
                    <div class="bar-container">
                        <div class="bar-fill" style="width: {{ $ancho }}%;"></div>
                    </div>
                </td>
                <td class="text-right">{{ round($m->tiempo_promedio) }} min</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <h3>C. Áreas con Mayor Incidencia (Hotspots Institucionales)</h3>
    <table>
        <thead>
            <tr>
                <th width="30%">Departamento / Área</th>
                <th width="50%">Frecuencia de Reportes</th>
                <th width="20%" class="text-right">Total Tickets</th>
            </tr>
        </thead>
        <tbody>
            @php $max_area = $areas_hotspots->max('total') ?? 1; @endphp
            @foreach($areas_hotspots as $ah)
            @php $ancho_area = ($ah->total / $max_area) * 100; @endphp
            <tr>
                <td>{{ $ah->area->name }}</td>
                <td>
                    <div class="bar-container">
                        <div class="bar-fill-red" style="width: {{ $ancho_area }}%;"></div>
                    </div>
                </td>
                <td class="text-right"><strong>{{ $ah->total }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 15px;">
        <tr>
            <td width="50%" style="vertical-align: top; padding-right: 15px; border: none;">
                <h3>D. Envejecimiento de Tickets</h3>
                <table style="border: 1px solid #eee;">
                    <tr>
                        <td>Frescos (< 24h)</td>
                        <td class="text-right"><span style="color: #27ae60; font-weight: bold;">{{ $aging['frescos'] }}</span></td>
                    </tr>
                    <tr>
                        <td>Normal (1-3 días)</td>
                        <td class="text-right"><span style="color: #f39c12; font-weight: bold;">{{ $aging['normal'] }}</span></td>
                    </tr>
                    <tr>
                        <td>Crítico (> 3 días)</td>
                        <td class="text-right"><span style="color: #c0392b; font-weight: bold;">{{ $aging['critico'] }}</span></td>
                    </tr>
                </table>
            </td>
            <td width="50%" style="vertical-align: top; padding-left: 15px; border: none;">
                <h3>E. Usuarios con Más Solicitudes</h3>
                <table style="border: 1px solid #eee;">
                    @foreach($top_usuarios as $top)
                    <tr>
                        <td>{{ $top->user->name }}</td>
                        <td class="text-right">{{ $top->total }} req.</td>
                    </tr>
                    @endforeach
                </table>
            </td>
        </tr>
    </table>

    <h3>F. Gestión de Infraestructura y Espacios Físicos</h3>
    <table style="border: none;">
        <tr>
            <td width="40%" style="border: none; vertical-align: top;">
                <div style="background-color: #f8f9fa; padding: 10px; border: 1px solid #ddd; text-align: center;">
                    <span style="font-size: 20px; font-weight: bold; color: #800000; display: block;">{{ $total_reservas }}</span>
                    <span style="font-size: 10px; color: #666;">Total Solicitudes de Reserva</span>
                    
                    <hr style="border: 0; border-top: 1px solid #ccc; margin: 10px 0;">
                    
                    <span style="font-size: 16px; font-weight: bold; color: #27ae60; display: block;">{{ $reservas_aprobadas }}</span>
                    <span style="font-size: 10px; color: #666;">Aprobadas y Confirmadas</span>
                </div>
            </td>
            <td width="60%" style="border: none; padding-left: 20px; vertical-align: top;">
                <strong>Top Ambientes Más Utilizados:</strong>
                <table style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th>Nombre del Ambiente</th>
                            <th class="text-right">Veces Reservado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($top_ambientes as $top)
                        <tr>
                            <td>{{ $top->ambiente->nombre }}</td>
                            <td class="text-right"><strong>{{ $top->total }}</strong></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="2" style="text-align: center; color: #999;">No hay reservas aprobadas en este periodo.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $titulo }} | Emitido por el Sistema Red UMSALUD | Usuario: {{ $generado_por }} | Fecha: {{ $fecha_emision }}
    </div>

</body>
</html>