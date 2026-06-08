<?php

namespace App\Exports;

use App\Models\Ticket;
use Maatwebsite\Excel\Concerns\FromCollection; // Permite exportar una colección de datos
use Maatwebsite\Excel\Concerns\WithHeadings; // Permite definir encabezados personalizados para las columnas
use Maatwebsite\Excel\Concerns\WithMapping; // Permite mapear cada fila de datos a un formato específico para la exportación
use Maatwebsite\Excel\Concerns\ShouldAutoSize;// Ajusta automáticamente el ancho de las columnas según su contenido
use Maatwebsite\Excel\Concerns\WithEvents; // Permite registrar eventos para personalizar el diseño y formato de la hoja
use Maatwebsite\Excel\Events\AfterSheet;  // Evento que se dispara después de que la hoja ha sido creada, para aplicar estilos personalizados
use PhpOffice\PhpSpreadsheet\Style\Border; // Permite aplicar estilos de borde a las celdas
use PhpOffice\PhpSpreadsheet\Style\Fill; // Permite aplicar estilos de relleno (color de fondo) a las celdas
use PhpOffice\PhpSpreadsheet\Style\Alignment; // Permite aplicar estilos de alineación a las celdas

class ReporteTickets implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithEvents
{
    protected $inicio;
    protected $fin;
    protected $tecnicoId;

    public function __construct($inicio, $fin, $tecnicoId = null)
    {
        $this->inicio = $inicio;
        $this->fin = $fin;
        $this->tecnicoId = $tecnicoId;
    }

    /**
     * Consulta a la base de datos
     */
    public function collection()
    {
        // consulta base: Tickets cerrados entre las fechas, con relaciones para mostrar nombres en lugar de IDs
        $query = Ticket::with(['user', 'assignedUser', 'colaborador', 'area', 'category'])
            ->where('status', 'cerrado')
            ->whereBetween('updated_at', [$this->inicio, $this->fin]);
        // Si se proporcionó un ID de técnico, filtramos para mostrar solo los tickets relacionados con ese técnico (ya sea como asignado o como colaborador)
        if ($this->tecnicoId) {
            $query->where(function ($q) {
                $q->where('assigned_to', $this->tecnicoId)
                  ->orWhere('colaborador_id', $this->tecnicoId);
            });
        }
        return $query->orderBy('updated_at', 'desc')->get();
    }

    /**
     * Encabezados de la tabla en Excel
     */
    public function headings(): array
    {
        return [
            'ID Ticket',
            'Asunto / Incidencia',
            'Solicitante (Usuario)',
            'Área / Ubicación',
            'Técnico Responsable',
            'Técnico Apoyo (Colaborador)',
            'Categoría',
            'Prioridad',
            'Fecha Resolución',
            'Tiempo (Minutos)'
        ];
    }

    /**
     * Mapeo de datos: Qué poner en cada fila
     */
    public function map($ticket): array
    {
        return [
            $ticket->id,
            $ticket->subject,
            $ticket->user->name ?? 'Desconocido',
            $ticket->area->name ?? 'Sin Área',
            $ticket->assignedTo->name ?? 'Sin Asignar',
            $ticket->colaborador->name ?? '---', // Si no hay colaborador, mostrar '---'
            $ticket->category->name ?? 'General',
            strtoupper($ticket->priority),
            $ticket->updated_at ? $ticket->updated_at->format('d/m/Y H:i') : '---',
            $ticket->minutes_spent ?? 0
        ];
    }

    /**
     * Eventos para personalizar el diseño y formato de la hoja después de que se ha creado
     */
    public function registerEvents(): array
    {
        return [
            // Este evento se dispara después de que la hoja ha sido creada, lo que nos permite aplicar estilos personalizados a toda la tabla
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                $rangoCompleto = 'A1:' . $highestColumn . $highestRow;

                // 1. Bordes Delgados a toda la tabla
                $sheet->getStyle($rangoCompleto)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFCCCCCC'], // Gris claro, más elegante que negro puro
                        ],
                    ],
                ]);

                // 2. Filtros activados
                $sheet->setAutoFilter('A1:' . $highestColumn . '1');

                // 3. Centrar columnas clave
                // A=ID, H=Prioridad, I=Fecha, J=Tiempo
                $sheet->getStyle('A2:A' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('H2:H' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('I2:I' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('J2:J' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // 4. Formato de Cabecera (Azul UMSA)
                $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['argb' => 'FFFFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF003366'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER, // Centrado vertical también
                    ]
                ]);

                // 5. Ajustar altura de la cabecera
                $sheet->getRowDimension(1)->setRowHeight(25);
            },
        ];
    }
}