<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReporteInventarioPropiedadesExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    public function __construct(
        private array  $rows,
        private array  $kpis,
        private string $fechaCorte
    ) {}

    public function title(): string
    {
        return 'Inventario Propiedades';
    }

    public function headings(): array
    {
        return [
            'Código',
            'Tipo',
            'Sector Urbano',
            'Distrito',
            'Dirección',
            'Sup. Terreno (m²)',
            'Sup. Construida (m²)',
            'Frente (m)',
            'Fondo (m)',
            'Hab.',
            'Baños',
            'Precio Venta',
            'Moneda',
            'Estado',
        ];
    }

    public function array(): array
    {
        $data = [];

        $data[] = ['Corte:', $this->fechaCorte, '', '', '', '', '', '', '', '', '', '', '', ''];
        $data[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', ''];

        $data[] = ['RESUMEN DEL INVENTARIO', '', '', '', '', '', '', '', '', '', '', '', '', ''];
        $data[] = [
            'Total Propiedades', $this->kpis['total'],
            '', '',
            'Disponibles', $this->kpis['disponibles'],
            '', '',
            'Vendidas', $this->kpis['vendidas'],
            '', '',
            'Reservadas', $this->kpis['reservadas'],
        ];
        $data[] = [
            'Valor Disponible (USD)', number_format($this->kpis['valor_disponible_usd'], 2),
            '', '',
            'Valor Disponible (Bs.)', number_format($this->kpis['valor_disponible_bob'], 2),
            '', '',
            'Sup. Prom. (m²)', number_format($this->kpis['superficie_promedio'], 2),
            '', '', '', '',
        ];
        $data[] = ['', '', '', '', '', '', '', '', '', '', '', '', '', ''];

        $data[] = ['DETALLE DEL INVENTARIO', '', '', '', '', '', '', '', '', '', '', '', '', ''];

        foreach ($this->rows as $r) {
            $data[] = [
                $r['codigo'] ?? '-',
                $r['tipo'],
                $r['sector_urbano'] ?? '-',
                $r['distrito'] ?? '-',
                $r['direccion'] ?? '-',
                number_format($r['superficie_m2'], 2),
                $r['superficie_construida_m2'] ? number_format($r['superficie_construida_m2'], 2) : '-',
                $r['frente_mts'] ? number_format($r['frente_mts'], 2) : '-',
                $r['fondo_mts']  ? number_format($r['fondo_mts'], 2)  : '-',
                $r['habitaciones'] ?? '-',
                $r['banos'] ?? '-',
                number_format($r['precio_venta'], 2),
                $r['moneda'],
                $r['estado'],
            ];
        }

        return $data;
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 14, 'C' => 22, 'D' => 20,
            'E' => 28, 'F' => 16, 'G' => 18, 'H' => 12,
            'I' => 12, 'J' => 6,  'K' => 8,  'L' => 18,
            'M' => 10, 'N' => 14,
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
