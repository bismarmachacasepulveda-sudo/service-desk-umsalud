<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use App\Exports\Hojas\GraficosSheet;
use App\Exports\Hojas\DatosSheet;

class ReporteGlobalExport implements WithMultipleSheets
{
    protected $inicio;
    protected $fin;

    /** 1. CONSTRUCTOR */
    public function __construct($inicio, $fin)
    {
        $this->inicio = $inicio;
        $this->fin = $fin;
    }

    /** 2. HOJAS */
    public function sheets(): array
    {
        $sheets = [];

        // 1. Primero la Hoja de Datos
        $sheets[] = new DatosSheet($this->inicio, $this->fin);

        // 2. Segundo la Hoja de Gráficos
        $sheets[] = new GraficosSheet($this->inicio, $this->fin);

        return $sheets;
    }
}