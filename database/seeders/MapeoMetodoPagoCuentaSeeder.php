<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\MetodoPagoCuentaDefault;
use App\Models\MetodoPago;
use App\Models\CuentaBancaria;
use App\Models\MiEmpresa;

class MapeoMetodoPagoCuentaSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = MiEmpresa::first();
        if (!$empresa) return;

        $metodos = MetodoPago::all();
        $cuentas = CuentaBancaria::where('mi_empresa_id', $empresa->id)->get();

        // Mapeos por defecto
        $mapeos = [
            'Efectivo' => 'Caja Principal',
            'Transferencia Bancaria' => 'Cuenta Corriente BCP',
            'Cheque' => 'Cuenta Corriente BCP',
            'Tarjeta de Crédito' => 'Stripe',
            'Depósito Bancario' => 'Cuenta de Ahorros BCP',
            'Letra de Cambio' => 'Cuenta Corriente BCP',
        ];

        foreach ($mapeos as $nombreMetodo => $nombreCuenta) {
            $metodo = $metodos->firstWhere('nombre_metodo', $nombreMetodo);
            $cuenta = $cuentas->firstWhere('nombre', $nombreCuenta);

            if ($metodo && $cuenta) {
                MetodoPagoCuentaDefault::firstOrCreate(
                    [
                        'mi_empresa_id' => $empresa->id,
                        'metodo_pago_id' => $metodo->id
                    ],
                    ['cuenta_bancaria_id' => $cuenta->id]
                );
            }
        }
    }
}
