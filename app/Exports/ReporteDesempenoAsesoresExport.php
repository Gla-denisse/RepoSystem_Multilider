<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReporteDesempenoAsesoresExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    public function __construct(
        private array  $ranking,
        private array  $kpis,
        private string $desde,
        private string $hasta
    ) {}

    public function title(): string
    {
        return 'Desempeño Asesores';
    }

    public function headings(): array
    {
        return [
            'Pos.',
            'Asesor',
            'Ventas (#)',
            'Monto Vendido (Bs.)',
            'Cobrado (Bs.)',
            'Comisión Estimada (Bs.)',
            'Cartera Vigente (Bs.)',
            'Mora (Bs.)',
            '% Mora / Cartera',
        ];
    }

    public function array(): array
    {
        $data = [];

        $data[] = ['Período:', $this->desde . ' al ' . $this->hasta, '', '', '', '', '', '', ''];
        $data[] = ['', '', '', '', '', '', '', '', ''];

        $data[] = ['RESUMEN DEL EQUIPO', '', '', '', '', '', '', '', ''];
        $data[] = ['Total Vendido (Bs.)',    number_format($this->kpis['total_vendido'], 2),    '', '', 'Total Cobrado (Bs.)', number_format($this->kpis['total_cobrado'], 2), '', '', ''];
        $data[] = ['Total Comisiones (Bs.)', number_format($this->kpis['total_comisiones'], 2), '', '', 'Asesores con ventas', $this->kpis['asesores_con_ventas'], '', '', ''];
        $data[] = ['', '', '', '', '', '', '', '', ''];

        $data[] = ['RANKING DE ASESORES', '', '', '', '', '', '', '', ''];

        foreach ($this->ranking as $i => $r) {
            $pctMora = $r['cartera_vigente'] > 0
                ? round($r['mora'] / $r['cartera_vigente'] * 100, 1)
                : 0;

            $data[] = [
                $i + 1,
                $r['nombre'],
                $r['ventas_cantidad'],
                number_format($r['monto_vendido'], 2),
                number_format($r['cobrado'], 2),
                number_format($r['comision_estimada'], 2),
                number_format($r['cartera_vigente'], 2),
                number_format($r['mora'], 2),
                $pctMora . '%',
            ];
        }

        return $data;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6, 'B' => 30, 'C' => 12,
            'D' => 22, 'E' => 18, 'F' => 24,
            'G' => 22, 'H' => 18, 'I' => 16,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $headerRow = 8;
        return [
            $headerRow => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0B2545']],
            ],
            3 => ['font' => ['bold' => true, 'size' => 12]],
            7 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
