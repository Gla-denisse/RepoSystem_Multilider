<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReporteComisionesExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    public function __construct(
        private array  $rows,
        private array  $kpis,
        private string $desde,
        private string $hasta
    ) {}

    public function title(): string
    {
        return 'Comisiones';
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Asesor',
            'N° Venta',
            'Propiedad (Código)',
            'Cliente',
            'Moneda',
            'Monto Comisión',
            'Estado',
            'Fecha Pago',
            'Comprobante',
            'Concepto',
        ];
    }

    public function array(): array
    {
        $data = [];

        $data[] = ['Período:', $this->desde . ' al ' . $this->hasta, '', '', '', '', '', '', '', '', ''];
        $data[] = ['', '', '', '', '', '', '', '', '', '', ''];

        // KPIs
        $data[] = ['RESUMEN DE COMISIONES', '', '', '', '', '', '', '', '', '', ''];
        $data[] = ['Pendiente Bs.',  number_format($this->kpis['pendiente_bs'], 2),  '', '', 'Pagado Bs.',  number_format($this->kpis['pagado_bs'], 2),  '', '', '', '', ''];
        $data[] = ['Pendiente USD.', number_format($this->kpis['pendiente_usd'], 2), '', '', 'Pagado USD.', number_format($this->kpis['pagado_usd'], 2), '', '', '', '', ''];
        $data[] = ['Total registros', $this->kpis['total_count'], '', '', '', '', '', '', '', '', ''];
        $data[] = ['', '', '', '', '', '', '', '', '', '', ''];

        $data[] = ['DETALLE DE COMISIONES', '', '', '', '', '', '', '', '', '', ''];

        foreach ($this->rows as $r) {
            $data[] = [
                $r['fecha'],
                $r['asesor'],
                '#' . $r['nota_venta_id'],
                $r['propiedad'],
                $r['cliente'],
                $r['moneda'],
                number_format($r['monto'], 2),
                $r['estado'],
                $r['fecha_pago'] ?? '',
                $r['comprobante'] ?? '',
                $r['concepto'],
            ];
        }

        return $data;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 28, 'C' => 10, 'D' => 18,
            'E' => 28, 'F' => 10, 'G' => 18, 'H' => 12,
            'I' => 14, 'J' => 18, 'K' => 30,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $headerRow = 9;
        return [
            $headerRow => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0B2545']],
            ],
            3 => ['font' => ['bold' => true, 'size' => 12]],
            8 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
