<?php

namespace App\Exports\Hojas;

use App\Models\Ticket;
use Maatwebsite\Excel\Concerns\FromCollection; // Permite exportar una colección de datos
use Maatwebsite\Excel\Concerns\WithHeadings; // Permite definir encabezados personalizados para las columnas
use Maatwebsite\Excel\Concerns\WithMapping; // Permite mapear cada fila de datos a un formato específico para la exportación
use Maatwebsite\Excel\Concerns\ShouldAutoSize; // Ajusta automáticamente el ancho de las columnas según su contenido
use Maatwebsite\Excel\Concerns\WithTitle; // Permite definir un título personalizado para la hoja de Excel
use Maatwebsite\Excel\Concerns\WithEvents; // Permite registrar eventos para personalizar el diseño y formato de la hoja
use Maatwebsite\Excel\Events\AfterSheet; // Evento que se dispara después de que la hoja ha sido creada, para aplicar estilos personalizados
use PhpOffice\PhpSpreadsheet\Style\Border; // Permite aplicar estilos de borde a las celdas
use PhpOffice\PhpSpreadsheet\Style\Fill; // Permite aplicar estilos de relleno (color de fondo) a las celdas
use PhpOffice\PhpSpreadsheet\Style\Alignment; // Permite aplicar estilos de alineación a las celdas

class DatosSheet implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithTitle, WithEvents
{
    protected $inicio; // Fecha de inicio para filtrar los tickets
    protected $fin; // Fecha de fin para filtrar los tickets

    /** ===================
    *1. CONSTRUCTOR
    *======================*/
    public function __construct($inicio, $fin)
    {
        $this->inicio = $inicio; //
        $this->fin = $fin;
    }
    /**======================
     * 2. TÍTULO DE LA HOJA
    *======================*/
    public function title(): string
    {
        return 'Detalle de Tickets';
    }
    /**======================
     * 3. COLECCIÓN DE DATOS
    *======================*/
    public function collection()
    {
        // CORRECCIÓN: Usar 'assignedTo' y agregar 'colaborador'
        return Ticket::with(['user', 'assignedUser', 'colaborador', 'area', 'category'])
            ->where('status', 'cerrado')
            ->whereBetween('updated_at', [$this->inicio, $this->fin])
            ->orderBy('updated_at', 'desc')
            ->get();
    }
    /**======================
     * 4. MAPEADO DE DATOS
    *======================*/
    public function map($ticket): array
    {
        return [
            $ticket->id,
            $ticket->subject,
            $ticket->user->name ?? 'Desconocido',
            $ticket->area->name ?? 'Sin Área',
            $ticket->assignedTo->name ?? 'Sin Asignar', // Mostrar el técnico asignado o 'Sin Asignar' si no hay ninguno
            $ticket->colaborador->name ?? '---', // Mostrar el colaborador asignado o '---' si no hay ninguno
            $ticket->category->name ?? 'General',
            strtoupper($ticket->priority), // Convertir la prioridad a mayúsculas para mejor visualización
            $ticket->updated_at ? $ticket->updated_at->format('d/m/Y H:i') : '---',
            $ticket->minutes_spent ?? 0
        ];
    }

    /**======================
     * 5. ENCABEZADOS DE COLUMNA
    *======================*/
    public function headings(): array
    {
        return [
            'ID', 'Asunto', 'Solicitante', 'Área', 
            'Técnico Principal', 'Colaborador', 'Categoría', 
            'Prioridad', 'Fecha Cierre', 'Tiempo (min)'
        ];
    }

    /**====================
     * 6. DISEÑO Y COLOR
     *======================*/
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();
                $range = 'A1:' . $highestColumn . $highestRow;

                // 1. ESTILO DEL ENCABEZADO
                $sheet->getStyle('A1:' . $highestColumn . '1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF003366']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // 2. FILTROS
                $sheet->setAutoFilter('A1:' . $highestColumn . '1');

                // 3. BORDES
                $sheet->getStyle($range)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['argb' => 'FFCCCCCC'], // Gris para no saturar la vista
                        ],
                    ],
                ]);

                // 4. ALINEACIÓN CENTRADA (Ajustado por la nueva columna)
                // H = Prioridad, I = Fecha, J = Tiempo
                $sheet->getStyle('A2:A' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 
                $sheet->getStyle('H2:J' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); 

                // 5. COLOREADO INTELIGENTE
                for ($row = 2; $row <= $highestRow; $row++) {
                    
                    // Efecto Cebra
                    if ($row % 2 == 0) {
                        $sheet->getStyle('A' . $row . ':' . $highestColumn . $row)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFF9F9F9');
                    }

                    // Semáforo de Prioridad (Ahora en Columna H)
                    $prioridad = $sheet->getCell('H' . $row)->getValue();
                    $colorTexto = 'FF000000'; 
                    $negrita = false;

                    if ($prioridad === 'ALTA') {
                        $colorTexto = 'FFC0392B'; // Rojo suave
                        $negrita = true;
                    } elseif ($prioridad === 'MEDIA') {
                        $colorTexto = 'FFD35400'; // Naranja
                    } elseif ($prioridad === 'BAJA') {
                        $colorTexto = 'FF27AE60'; // Verde
                    }

                    $sheet->getStyle('H' . $row)->applyFromArray([
                        'font' => ['color' => ['argb' => $colorTexto], 'bold' => $negrita]
                    ]);
                }
            },
        ];
    }
}