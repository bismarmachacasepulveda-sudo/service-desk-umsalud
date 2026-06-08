<?php

namespace App\Exports\Hojas;

use App\Models\Ticket;
use Maatwebsite\Excel\Concerns\FromCollection; // Permite exportar una colección de datos
use Maatwebsite\Excel\Concerns\WithTitle; // Permite definir un título personalizado para la hoja de Excel
use Maatwebsite\Excel\Concerns\WithCharts; // Permite agregar gráficos a la hoja de Excel
use Maatwebsite\Excel\Concerns\WithHeadings; // Permite definir encabezados personalizados para las columnas
use Maatwebsite\Excel\Concerns\WithEvents; // Necesario para estilos
use Maatwebsite\Excel\Events\AfterSheet; // Evento para aplicar estilos después de crear la hoja
use PhpOffice\PhpSpreadsheet\Chart\Chart; // Permite crear gráficos personalizados
use PhpOffice\PhpSpreadsheet\Chart\DataSeries; // Permite definir las series de datos para los gráficos
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues; // Permite definir los valores de las series de datos para los gráficos
use PhpOffice\PhpSpreadsheet\Chart\Legend; // Permite agregar una leyenda a los gráficos
use PhpOffice\PhpSpreadsheet\Chart\PlotArea; // Permite definir el área de trazado de los gráficos
use PhpOffice\PhpSpreadsheet\Chart\Title; // Permite agregar un título a los gráficos
use PhpOffice\PhpSpreadsheet\Style\Border; // Permite aplicar estilos de borde a las celdas
use PhpOffice\PhpSpreadsheet\Style\Fill; // Permite aplicar estilos de relleno (color de fondo) a las celdas
use PhpOffice\PhpSpreadsheet\Style\Alignment; // Permite aplicar estilos de alineación a las celdas
use Illuminate\Support\Facades\DB;// Para realizar consultas más complejas con agregaciones

class GraficosSheet implements FromCollection, WithTitle, WithCharts, WithHeadings, WithEvents
{
    protected $inicio;
    protected $fin;

    /** ===================
     * 1. CONSTRUCTOR
    *======================*/
    public function __construct($inicio, $fin)
    {
        $this->inicio = $inicio;
        $this->fin = $fin;
    }

    /** ===================
     * 2. TÍTULO DE LA HOJA
    *======================*/
    public function title(): string
    {
        return 'Resumen Grafico';
    }

    public function headings(): array
    {
        return [
            'Categoría', 'Total', '', 
            'Estado', 'Total',    '', 
            'Prioridad', 'Total'
        ];
    }

    /**======================
     * 3. COLECCIÓN DE DATOS
    *======================*/
    public function collection()
    {
        // 1. Datos por Categoría
        $dataCat = Ticket::select('categories.name', DB::raw('count(*) as total'))
            ->join('categories', 'tickets.category_id', '=', 'categories.id') // Unimos con la tabla de categorías para obtener el nombre
            ->whereBetween('tickets.created_at', [$this->inicio, $this->fin])
            ->groupBy('categories.name')->get();

        // 2. Datos por Estado
        $dataStatus = Ticket::select('status', DB::raw('count(*) as total'))
            ->whereBetween('created_at', [$this->inicio, $this->fin])
            ->groupBy('status')->get();

        // 3. Datos por Prioridad
        $dataPrio = Ticket::select('priority', DB::raw('count(*) as total'))
            ->whereBetween('created_at', [$this->inicio, $this->fin])
            ->groupBy('priority')->get();

        // Unificar filas
        $maxRows = max($dataCat->count(), $dataStatus->count(), $dataPrio->count());
        $rows = [];

        for ($i = 0; $i < $maxRows; $i++) {
            $rows[] = [
                isset($dataCat[$i]) ? $dataCat[$i]->name : null,
                isset($dataCat[$i]) ? $dataCat[$i]->total : null,
                '', 
                isset($dataStatus[$i]) ? strtoupper($dataStatus[$i]->status) : null,
                isset($dataStatus[$i]) ? $dataStatus[$i]->total : null,
                '', 
                isset($dataPrio[$i]) ? strtoupper($dataPrio[$i]->priority) : null,
                isset($dataPrio[$i]) ? $dataPrio[$i]->total : null,
            ];
        }

        return collect($rows);
    }

    public function charts()
    {     
        $count = Ticket::whereBetween('created_at', [$this->inicio, $this->fin])->count();
        if ($count === 0) return [];

        // --- GRÁFICO 1: CATEGORÍAS ---
        $charts[] = $this->crearGrafico('Por Categoría', 'cat_chart', DataSeries::TYPE_PIECHART, 'A', 'B', 10, 'A12', 'E27');
        // --- GRÁFICO 2: ESTADO ---
        $charts[] = $this->crearGrafico('Por Estado', 'status_chart', DataSeries::TYPE_DONUTCHART, 'D', 'E', 5, 'F12', 'J27');
        // --- GRÁFICO 3: PRIORIDAD ---
        $charts[] = $this->crearGrafico('Por Prioridad', 'prio_chart', DataSeries::TYPE_BARCHART, 'G', 'H', 5, 'A29', 'J44');

        return $charts;
    }

    // Función auxiliar para gráficos 
    private function crearGrafico($titulo, $nombre, $tipo, $colLabel, $colValue, $maxRows, $posTop, $posBottom) {
        $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Resumen Grafico'!\${$colLabel}$2:\${$colLabel}$" . ($maxRows + 1), null, $maxRows)];
        $values = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Resumen Grafico'!\${$colValue}$2:\${$colValue}$" . ($maxRows + 1), null, $maxRows)];
        $series = new DataSeries($tipo, null, range(0, count($values) - 1), [], $labels, $values);
        $layout = new \PhpOffice\PhpSpreadsheet\Chart\Layout();
    if ($tipo === DataSeries::TYPE_PIECHART || $tipo === DataSeries::TYPE_DONUTCHART) {
            // Para Tortas y Donas: Queremos el porcentaje real
            $layout->setShowVal(false);     // Ocultamos el número entero (ej: 5)
            $layout->setShowPercent(true);  // Excel calcula el % real (ej: 50%)
        } else {
            // Para Barras: Solo queremos ver la cantidad exacta
            $layout->setShowVal(true);      // Mostramos el número entero (ej: 5)
            $layout->setShowPercent(false); // Apagamos el % para que no invente datos
        }
        
        $chart = new Chart(
            $nombre, 
            new Title($titulo), 
            new Legend(Legend::POSITION_RIGHT, null, false), 
            new PlotArea($layout, [$series])
        );
        
        $chart->setTopLeftPosition($posTop);
        $chart->setBottomRightPosition($posBottom);
        
        return $chart;
    }

    /**
     * 🎨 DISEÑO DE LAS TABLAS
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // Estilo Base de Encabezados (Azul UMSA)
                $headerStyle = [
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF003366']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ];

                // Estilo de Bordes
                $borderStyle = [
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']],
                    ],
                ];

                // APLICAR ESTILOS A LAS 3 TABLAS POR SEPARADO
                
                // Tabla 1: Categorías (A-B)
                $sheet->getStyle('A1:B1')->applyFromArray($headerStyle);
                $sheet->getStyle('A1:B' . $highestRow)->applyFromArray($borderStyle);
                $sheet->getColumnDimension('A')->setAutoSize(true);
                $sheet->getColumnDimension('B')->setWidth(10);

                // Tabla 2: Estado (D-E)
                $sheet->getStyle('D1:E1')->applyFromArray($headerStyle);
                $sheet->getStyle('D1:E' . $highestRow)->applyFromArray($borderStyle);
                $sheet->getColumnDimension('D')->setAutoSize(true);
                $sheet->getColumnDimension('E')->setWidth(10);

                // Tabla 3: Prioridad (G-H)
                $sheet->getStyle('G1:H1')->applyFromArray($headerStyle);
                $sheet->getStyle('G1:H' . $highestRow)->applyFromArray($borderStyle);
                $sheet->getColumnDimension('G')->setAutoSize(true);
                $sheet->getColumnDimension('H')->setWidth(10);

                // Centrar los números (Columnas de Totales)
                $sheet->getStyle('B2:B' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('E2:E' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('H2:H' . $highestRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}