<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReporteCarteraMoraExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    public function __construct(
        private array  $rows,
        private array  $kpis,
        private array  $aging,
        private string $fechaCorte
    ) {}

    public function title(): string
    {
        return 'Cartera y Mora';
    }

    public function headings(): array
    {
        return [
            'Cliente',
            'Asesor',
            'N° Cuota',
            'Fecha Vencimiento',
            'Días de Mora',
            'Tramo',
            'Monto Cuota (Bs.)',
            'Saldo Capital (Bs.)',
        ];
    }

    public function array(): array
    {
        $data = [];

        $data[] = ['Corte al:', $this->fechaCorte, '', '', '', '', '', ''];
        $data[] = ['', '', '', '', '', '', '', ''];

        // KPIs
        $data[] = ['RESUMEN CARTERA', '', '', '', '', '', '', ''];
        $data[] = ['Cartera Total Activa (Bs.)', number_format($this->kpis['cartera_total'], 2), '', '', 'Clientes en Mora', $this->kpis['clientes_en_mora'], '', ''];
        $data[] = ['Monto Total Vencido (Bs.)', number_format($this->kpis['monto_vencido'], 2), '', '', 'Cuotas Vencidas', $this->kpis['cuotas_vencidas_count'], '', ''];
        $data[] = ['', '', '', '', '', '', '', ''];

        // Tabla aging
        $data[] = ['ANÁLISIS DE MORA (AGING)', '', '', '', '', '', '', ''];
        $data[] = ['Tramo', '', 'Cuotas', '', 'Monto (Bs.)', '', '% del Total', ''];

        $totalMonto = array_sum(array_column($this->aging, 'monto'));
        foreach ($this->aging as $tramo => $d) {
            $pct = $totalMonto > 0 ? round($d['monto'] / $totalMonto * 100, 1) : 0;
            $data[] = [$tramo, '', $d['count'], '', number_format($d['monto'], 2), '', $pct . '%', ''];
        }

        $data[] = ['', '', '', '', '', '', '', ''];

        // Detalle
        $data[] = ['DETALLE DE CUOTAS VENCIDAS', '', '', '', '', '', '', ''];
        foreach ($this->rows as $r) {
            $data[] = [
                $r['cliente'],
                $r['asesor'],
                'N° ' . $r['numero_cuota'],
                $r['fecha_vencimiento'],
                $r['dias_mora'],
                $r['tramo'],
                number_format($r['monto_cuota'], 2),
                number_format($r['saldo_capital'], 2),
            ];
        }

        return $data;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 30,
            'B' => 25,
            'C' => 12,
            'D' => 18,
            'E' => 14,
            'F' => 16,
            'G' => 18,
            'H' => 18,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $headerRow = 10;
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
