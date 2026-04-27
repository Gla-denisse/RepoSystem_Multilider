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
use App\Models\Manzano;
use App\Models\Ubicacion;
use App\Models\Propiedad;

class DatosPruebaSeeder extends Seeder
{
    public function run(): void
    {
        // ==========================================
        // 1. POBLAR ASESORES (Vinculados a los usuarios del DatabaseSeeder)
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
        // 2. POBLAR CLIENTES (Con la regla de Contraseña = CI)
        // ==========================================
        $rolCliente = Rol::where('nombre', 'Cliente')->first();
        $permisosCliente = RolPermiso::where('rol_id', $rolCliente->id)->get();

        $clientesDemo = [
            ['ci' => '8989891', 'exp' => 'SC', 'nombre' => 'Roberto Carlos Diaz', 'correo' => 'roberto@gmail.com', 'tel' => '71122334'],
            ['ci' => '5454542', 'exp' => 'CB', 'nombre' => 'Ana Maria Salvatierra', 'correo' => 'ana.maria@hotmail.com', 'tel' => '72233445']
        ];

        foreach ($clientesDemo as $c) {
            // A) Crear el Usuario (Pass = CI)
            $userCliente = User::firstOrCreate(
                ['correo' => $c['correo']],
                ['nombre' => $c['nombre'], 'password' => Hash::make($c['ci']), 'estado' => true]
            );

            // B) Asignar Permisos del Rol Cliente
            foreach ($permisosCliente as $permiso) {
                RolPermisoUsuario::firstOrCreate(['user_id' => $userCliente->id, 'rol_permiso_id' => $permiso->id]);
            }

            // C) Crear el Perfil de Cliente
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
        // 4. POBLAR MANZANOS
        // ==========================================
        $manzanos = [
            ['codigo' => 'MZ-A', 'descripcion' => 'Manzano Fase 1 - Frente a la plaza principal'],
            ['codigo' => 'MZ-B', 'descripcion' => 'Manzano Fase 1 - Zona Este'],
            ['codigo' => 'MZ-C', 'descripcion' => 'Manzano Fase 2 - Cerca de la avenida']
        ];

        foreach ($manzanos as $mz) {
            Manzano::firstOrCreate(['codigo' => $mz['codigo']], ['descripcion' => $mz['descripcion'], 'estado' => true]);
        }

        // ==========================================
        // 5. POBLAR PROPIEDADES (Con Ubicaciones simuladas)
        // ==========================================
        $manzanoA = Manzano::where('codigo', 'MZ-A')->first();
        $manzanoB = Manzano::where('codigo', 'MZ-B')->first();

        // Propiedad 1
        $ubi1 = Ubicacion::create([
            'referencia' => 'Esquina principal, frente al parque',
            'url_maps' => 'https://www.google.com/maps/search/?api=1&query=$-17.338062,-63.245930',
            'latitud' => '-17.338062', 'longitud' => '-63.245930'
        ]);

        Propiedad::firstOrCreate(
            ['codigo' => 'LOTE-A01'],
            [
                'propietario_id' => $propietario1->id, 'manzano_id' => $manzanoA->id, 'ubicacion_id' => $ubi1->id,
                'tipo' => 'Lote', 'precio_venta' => 150000, 'direccion' => 'Calle Los Tajibos Esq. Las Palmas',
                'nro_lote' => '1', 'superficie_m2' => 350.50,
                'colinda_norte' => 'Calle Las Palmas', 'colinda_sur' => 'Lote 2', 
                'colinda_este' => 'Calle Los Tajibos', 'colinda_oeste' => 'Lote 14',
                'estado' => 'Disponible', 'activo' => true
            ]
        );

        // Propiedad 2
        $ubi2 = Ubicacion::create([
            'referencia' => 'A media cuadra de la avenida',
            'url_maps' => 'https://www.google.com/maps/search/?api=1&query=$-17.339123,-63.246111',
            'latitud' => '-17.339123', 'longitud' => '-63.246111'
        ]);

        Propiedad::firstOrCreate(
            ['codigo' => 'LOTE-B05'],
            [
                'propietario_id' => $propietario2->id, 'manzano_id' => $manzanoB->id, 'ubicacion_id' => $ubi2->id,
                'tipo' => 'Lote', 'precio_venta' => 125000, 'direccion' => 'Calle 3, Barrio Nuevo',
                'nro_lote' => '5', 'superficie_m2' => 300.00,
                'colinda_norte' => 'Lote 4', 'colinda_sur' => 'Lote 6', 
                'colinda_este' => 'Avenida Central', 'colinda_oeste' => 'Lote 10',
                'estado' => 'Reservado', 'activo' => true
            ]
        );
    }
}