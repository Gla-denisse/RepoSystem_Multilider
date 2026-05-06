<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Rol;
use App\Models\RolPermiso;
use App\Models\RolPermisoUsuario;
use App\Models\Asesor;
use App\Models\Cliente;
use App\Models\Propietario;
use App\Models\Ciudad;
use App\Models\Zona;
use App\Models\Caracteristica;
use App\Models\Ubicacion;
use App\Models\Propiedad;

class DatosPruebaSeeder extends Seeder
{
    public function run(): void
    {
        // ==========================================
        // 1. POBLAR ASESORES (Vinculados a los usuarios)
        // ==========================================
        $carlos = User::where('correo', 'carlos@tecnoweb.edu')->first();
        if ($carlos) {
            Asesor::firstOrCreate(
                ['user_id' => $carlos->id],
                [
                    'nombre_completo' => $carlos->nombre,
                    'telefono' => '77711122',
                    'correo' => $carlos->correo,
                    'direccion' => 'Av. Principal, Montero',
                    'estado' => true
                ]
            );
        }

        $laura = User::where('correo', 'laura@tecnoweb.edu')->first();
        if ($laura) {
            Asesor::firstOrCreate(
                ['user_id' => $laura->id],
                [
                    'nombre_completo' => $laura->nombre,
                    'telefono' => '77733344',
                    'correo' => $laura->correo,
                    'direccion' => 'Barrio Centro, Santa Cruz',
                    'estado' => true
                ]
            );
        }

        // ==========================================
        // 2. POBLAR CLIENTES
        // ==========================================
        $rolCliente = Rol::where('nombre', 'Cliente')->first();
        $permisosCliente = RolPermiso::where('rol_id', $rolCliente->id)->get();

        $clientesDemo = [
            ['ci' => '8989891', 'exp' => 'SC', 'nombre' => 'Roberto Carlos Diaz', 'correo' => 'roberto@gmail.com', 'tel' => '71122334'],
            ['ci' => '5454542', 'exp' => 'CB', 'nombre' => 'Ana Maria Salvatierra', 'correo' => 'ana.maria@hotmail.com', 'tel' => '72233445']
        ];

        foreach ($clientesDemo as $c) {
            $userCliente = User::firstOrCreate(
                ['correo' => $c['correo']],
                ['nombre' => $c['nombre'], 'password' => Hash::make($c['ci']), 'estado' => true]
            );

            foreach ($permisosCliente as $permiso) {
                RolPermisoUsuario::firstOrCreate(['user_id' => $userCliente->id, 'rol_permiso_id' => $permiso->id]);
            }

            Cliente::firstOrCreate(
                ['ci' => $c['ci']],
                [
                    'user_id' => $userCliente->id,
                    'lugar_expedicion' => $c['exp'],
                    'nombre_completo' => $c['nombre'],
                    'telefono' => $c['tel'],
                    'correo' => $c['correo'],
                    'direccion' => 'Zona Norte, 3er Anillo',
                    'estado' => true
                ]
            );
        }

        // ==========================================
        // 3. POBLAR PROPIETARIOS
        // ==========================================
        $propietario1 = Propietario::firstOrCreate(
            ['ci' => '10203040'],
            [
                'lugar_expedicion' => 'SC', 'nombre_completo' => 'Inversiones Inmobiliarias S.A.',
                'telefono' => '33445566', 'correo' => 'gerencia@inversiones.com.bo',
                'direccion' => 'Edificio Empresarial, Piso 5', 'estado' => true
            ]
        );

        $propietario2 = Propietario::firstOrCreate(
            ['ci' => '40302010'],
            [
                'lugar_expedicion' => 'LP', 'nombre_completo' => 'Grupo Constructor El Bosque',
                'telefono' => '22334455', 'correo' => 'ventas@elbosque.com',
                'direccion' => 'Av. Las Americas #123', 'estado' => true
            ]
        );

        // ==========================================
        // 4. NUEVO: POBLAR CIUDADES, ZONAS Y CARACTERÍSTICAS
        // ==========================================
        $ciudadWarnes = Ciudad::firstOrCreate(['nombre' => 'Warnes'], ['departamento' => 'Santa Cruz']);
        $ciudadMontero = Ciudad::firstOrCreate(['nombre' => 'Montero'], ['departamento' => 'Santa Cruz']);

        $zonaSatelite = Zona::firstOrCreate(['nombre' => 'Zona Juan Pablo II, Satélite Norte'], ['ciudad_id' => $ciudadWarnes->id]);
        $zonaEsterita = Zona::firstOrCreate(['nombre' => 'Urb. Villa Esterita'], ['ciudad_id' => $ciudadMontero->id]);

        $cGas = Caracteristica::firstOrCreate(['nombre' => 'Gas Domiciliario'], ['tipo' => 'Servicios']);
        $cTransporte = Caracteristica::firstOrCreate(['nombre' => 'Transporte Público Cercano'], ['tipo' => 'Entorno']);
        $cColegio = Caracteristica::firstOrCreate(['nombre' => 'Colegios Cercanos'], ['tipo' => 'Entorno']);
        $cAreasVerdes = Caracteristica::firstOrCreate(['nombre' => 'Áreas Verdes'], ['tipo' => 'Entorno']);
        $cLavanderia = Caracteristica::firstOrCreate(['nombre' => 'Área de Lavandería'], ['tipo' => 'Interna']);

        // ==========================================
        // 5. POBLAR PROPIEDADES (Con nueva estructura)
        // ==========================================
        
        // Propiedad 1: Casa en Warnes (Con Ubicación simulada)
        $ubi1 = Ubicacion::firstOrCreate(
            ['latitud' => '-17.514333'],
            [
                'referencia' => 'A 1½ cuadra de la carretera Warnes-SCZ',
                'url_maps' => 'https://www.google.com/maps/search/?api=1&query=$-17.338062,-63.245930',
                'longitud' => '-63.167232'
            ]
        );

        $casaWarnes = Propiedad::firstOrCreate(
            ['codigo' => 'CASA-WAR-001'],
            [
                'propietario_id' => $propietario1->id, 
                'zona_id' => $zonaSatelite->id, 
                'ubicacion_id' => $ubi1->id,
                'tipo' => 'Casa', 
                'precio_venta' => 47000.00, 
                'moneda' => 'USD',
                'superficie_m2' => 300.00,
                'superficie_construida_m2' => 78.00,
                'habitaciones' => 3,
                'banos' => 2,
                'es_esquina' => false,
                'direccion' => 'Calle Principal Sur',
                'estado' => 'Disponible', 
                'activo' => true
            ]
        );
        $casaWarnes->caracteristicas()->sync([$cGas->id, $cLavanderia->id, $cTransporte->id]);

        // Propiedad 2: Lote en Montero
        $ubi2 = Ubicacion::firstOrCreate(
            ['latitud' => '-17.339123'],
            [
                'referencia' => 'Terreno en esquina, Urb. Villa Esterita',
                'url_maps' => 'https://www.google.com/maps/search/?api=1&query=$-17.339123,-63.246111',
                'longitud' => '-63.246111'
            ]
        );

        $loteMontero = Propiedad::firstOrCreate(
            ['codigo' => 'LOTE-MON-001'],
            [
                'propietario_id' => $propietario2->id, 
                'zona_id' => $zonaEsterita->id, 
                'ubicacion_id' => $ubi2->id,
                'tipo' => 'Lote', 
                'precio_venta' => 13500.00, 
                'moneda' => 'USD',
                'superficie_m2' => 450.00,
                'superficie_construida_m2' => 0.00,
                'frente_mts' => 15.00,
                'fondo_mts' => 30.00,
                'habitaciones' => 0,
                'banos' => 0,
                'es_esquina' => true,
                'direccion' => 'Carretera a Saavedra, Esquina Av. Principal',
                'nro_lote' => '1',
                'colinda_norte' => 'Lote 2',
                'colinda_este' => 'Avenida Principal',
                'estado' => 'Disponible', 
                'activo' => true
            ]
        );
        $loteMontero->caracteristicas()->sync([$cTransporte->id, $cAreasVerdes->id, $cColegio->id]);
    }
}