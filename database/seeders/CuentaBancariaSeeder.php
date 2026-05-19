<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\CuentaBancaria;
use App\Models\MiEmpresa;

class CuentaBancariaSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = MiEmpresa::first();

        if (!$empresa) {
            return;
        }

        $cuentas = [
            // Para método: Efectivo
            [
                'nombre'      => 'Caja Principal',
                'tipo'        => 'EFECTIVO',
                'descripcion' => 'Caja de efectivo principal de la oficina',
                'saldo_inicial' => 0,
                'estado'      => 'Activa',
            ],
            // Para método: Transacción QR
            [
                'nombre'         => 'Cuenta QR Bancaria',
                'tipo'           => 'BANCARIA',
                'descripcion'    => 'Cuenta bancaria receptora de pagos vía código QR',
                'banco'          => 'Banco',
                'numero_cuenta'  => '0000000000',
                'titular'        => 'Sistema Multilider',
                'saldo_inicial'  => 0,
                'estado'         => 'Activa',
            ],
            // Para método: Pasarela de Pago
            [
                'nombre'             => 'Pasarela de Pago',
                'tipo'               => 'DIGITAL',
                'descripcion'        => 'Cuenta de pasarela de pago en línea',
                'proveedor'          => 'Pasarela',
                'codigo_integracion' => '',
                'saldo_inicial'      => 0,
                'estado'             => 'Activa',
            ],
        ];

        foreach ($cuentas as $cuenta) {
            $cuenta['mi_empresa_id'] = $empresa->id;
            CuentaBancaria::firstOrCreate(
                ['mi_empresa_id' => $empresa->id, 'nombre' => $cuenta['nombre']],
                $cuenta
            );
        }
    }
}
