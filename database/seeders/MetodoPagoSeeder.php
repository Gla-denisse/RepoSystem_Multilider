<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\MetodoPago;

class MetodoPagoSeeder extends Seeder
{
    // Métodos activos en el sistema
    private array $activos = [
        'Efectivo',
        'Transacción QR',
        'Pasarela de Pago',
    ];

    public function run(): void
    {
        // Crear o activar los métodos válidos
        foreach ($this->activos as $nombre) {
            MetodoPago::updateOrCreate(
                ['nombre_metodo' => $nombre],
                ['estado' => 'Activo']
            );
        }

        // Desactivar cualquier otro método que exista en la DB
        MetodoPago::whereNotIn('nombre_metodo', $this->activos)
            ->update(['estado' => 'Inactivo']);
    }
}
