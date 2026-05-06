<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\MetodoPago;

class MetodoPagoSeeder extends Seeder
{
    public function run(): void
    {
        $metodos = [
            ['nombre_metodo' => 'Efectivo', 'estado' => 'Activo'],
            ['nombre_metodo' => 'Transferencia Bancaria', 'estado' => 'Activo'],
            ['nombre_metodo' => 'Cheque', 'estado' => 'Activo'],
            ['nombre_metodo' => 'Tarjeta de Crédito', 'estado' => 'Activo'],
            ['nombre_metodo' => 'Depósito Bancario', 'estado' => 'Activo'],
            ['nombre_metodo' => 'Letra de Cambio', 'estado' => 'Activo'],
        ];

        foreach ($metodos as $metodo) {
            MetodoPago::create($metodo);
        }
    }
}
