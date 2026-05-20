<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReporteVentasCobrosExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    public function __construct(
        private array $rows,
        private array $kpis,
        private string $desde,
        private string $hasta
    ) {}

    public function title(): string
    {
        return 'Ventas y Cobros';
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'N° Nota',
            'Cliente',
            'Asesor',
            'Propiedad (Código)',
            'Tipo Venta',
            'Monto Total (Bs.)',
            'Cobrado (Bs.)',
            'Saldo Crédito (Bs.)',
            'Estado',
        ];
    }

    public function array(): array
    {
        $data = [];

        // Fila informativa del período
        $data[] = ['Período:', $this->desde . ' al ' . $this->hasta, '', '', '', '', '', '', '', ''];
        $data[] = ['', '', '', '', '', '', '', '', '', ''];

        // KPIs resumen
        $data[] = ['RESUMEN DEL PERÍODO', '', '', '', '', '', '', '', '', ''];
        $data[] = ['Total Ventas (Bs.)',    number_format($this->kpis['total_ventas_monto'], 2),    '', '', '', 'Cant. Ventas', $this->kpis['total_ventas_cantidad'], '', '', ''];
        $data[] = ['Ventas Contado (Bs.)', number_format($this->kpis['ventas_contado_monto'], 2),  '', '', '', 'Total Cobrado (Bs.)', number_format($this->kpis['total_cobrado'], 2), '', '', ''];
        $data[] = ['Ventas Crédito (Bs.)', number_format($this->kpis['ventas_credito_monto'], 2),  '', '', '', '', '', '', '', ''];
        $data[] = ['', '', '', '', '', '', '', '', '', ''];

        // Detalle
        $data[] = ['DETALLE DE VENTAS', '', '', '', '', '', '', '', '', ''];

        foreach ($this->rows as $r) {
            $fecha = date('d/m/Y', strtotime($r['fecha']));
            $data[] = [
                $fecha,
                '#' . $r['id'],
                $r['cliente'],
                $r['asesor'],
                $r['propiedad'],
                $r['tipo_venta'],
                number_format($r['monto_total'], 2),
                number_format($r['cobrado'], 2),
                number_format($r['saldo_credito'], 2),
                $r['estado'],
            ];
        }

        return $data;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14,
            'B' => 10,
            'C' => 30,
            'D' => 25,
            'E' => 16,
            'F' => 14,
            'G' => 18,
            'H' => 18,
            'I' => 18,
            'J' => 12,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // Fila de encabezado de columnas (fila 10 — después de las 8 filas de info + 1 encabezado)
        $headerRow = 10;

        return [
            $headerRow => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FF0B2545'],
                ],
            ],
            3 => ['font' => ['bold' => true, 'size' => 12]],
            8 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
