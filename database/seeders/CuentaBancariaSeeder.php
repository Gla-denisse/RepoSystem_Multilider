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
            [
                'nombre' => 'Caja Principal',
                'tipo' => 'EFECTIVO',
                'descripcion' => 'Caja de efectivo principal de la oficina',
                'saldo_inicial' => 5000,
                'estado' => 'Activa'
            ],
            [
                'nombre' => 'Cuenta Corriente BCP',
                'tipo' => 'BANCARIA',
                'descripcion' => 'Cuenta corriente para transferencias',
                'banco' => 'Banco de Crédito del Perú',
                'numero_cuenta' => '1234567890',
                'titular' => 'Sistema Multilider',
                'iban' => 'PE91BCRAAA000000000000000',
                'saldo_inicial' => 10000,
                'estado' => 'Activa'
            ],
            [
                'nombre' => 'Cuenta de Ahorros BCP',
                'tipo' => 'BANCARIA',
                'descripcion' => 'Cuenta de ahorros para reservas',
                'banco' => 'Banco de Crédito del Perú',
                'numero_cuenta' => '0987654321',
                'titular' => 'Sistema Multilider',
                'iban' => 'PE91BCRAAA000000000000001',
                'saldo_inicial' => 20000,
                'estado' => 'Activa'
            ],
            [
                'nombre' => 'Stripe',
                'tipo' => 'DIGITAL',
                'descripcion' => 'Plataforma de pagos en línea Stripe',
                'proveedor' => 'Stripe',
                'codigo_integracion' => 'acct_1234567890',
                'saldo_inicial' => 0,
                'estado' => 'Activa'
            ],
            [
                'nombre' => 'PayPal',
                'tipo' => 'DIGITAL',
                'descripcion' => 'Cuenta PayPal para pagos internacionales',
                'proveedor' => 'PayPal',
                'codigo_integracion' => 'merchant_id_12345',
                'saldo_inicial' => 0,
                'estado' => 'Activa'
            ],
            [
                'nombre' => 'Mercado Pago',
                'tipo' => 'DIGITAL',
                'descripcion' => 'Mercado Pago para pagos locales',
                'proveedor' => 'Mercado Pago',
                'codigo_integracion' => 'APP_USR_1234567890',
                'saldo_inicial' => 0,
                'estado' => 'Activa'
            ],
        ];

        foreach ($cuentas as $cuenta) {
            $cuenta['mi_empresa_id'] = $empresa->id;
            CuentaBancaria::create($cuenta);
        }
    }
}
