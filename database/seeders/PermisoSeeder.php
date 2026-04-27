<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\RolPermiso;

class PermisoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Definimos y creamos los Roles
        $rolAdmin = Rol::firstOrCreate(
            ['nombre' => 'Administrador'], 
            ['descripcion' => 'Acceso total al sistema y configuraciones']
        );
        
        $rolAsesor = Rol::firstOrCreate(
            ['nombre' => 'Asesor de Ventas'], 
            ['descripcion' => 'Acceso operativo a clientes y propiedades']
        );

        $rolCliente = Rol::firstOrCreate(
            ['nombre' => 'Cliente'], 
            ['descripcion' => 'Acceso exclusivo al portal del cliente para ver sus lotes y pagos']
        );

        // 2. UN SOLO PERMISO POR MÓDULO (Estandarizado a "acceso_")
        $permisos = [
            // Módulo de Seguridad
            ['nombre' => 'acceso_usuarios', 'descripcion' => 'Acceso completo al módulo de Usuarios'],
            ['nombre' => 'acceso_roles', 'descripcion' => 'Acceso completo al módulo de Roles'],
            ['nombre' => 'acceso_permisos', 'descripcion' => 'Acceso completo al módulo de Permisos'],
            
            // Módulo de Gestión Operativa
            ['nombre' => 'acceso_propietarios', 'descripcion' => 'Acceso completo al módulo de Propietarios'],
            ['nombre' => 'acceso_manzanos', 'descripcion' => 'Acceso completo al módulo de Manzanos'],
            ['nombre' => 'acceso_propiedades', 'descripcion' => 'Acceso completo al módulo de Propiedades'],

            // Módulo de Gestión Comercial
            ['nombre' => 'acceso_asesores', 'descripcion' => 'Acceso completo al módulo de Asesores de Ventas'],
            ['nombre' => 'acceso_clientes', 'descripcion' => 'Acceso completo al módulo de Clientes'],
            ['nombre' => 'acceso_ventas', 'descripcion' => 'Acceso para registrar y anular notas de ventas y planes de pago'],
            ['nombre' => 'acceso_historial_ventas', 'descripcion' => 'Acceso para ver y filtrar el historial de ventas registradas'],
        ];

        // 3. Insertamos los permisos en la base de datos
        foreach ($permisos as $p) {
            Permiso::firstOrCreate(['nombre' => $p['nombre']], $p);
        }

        // 4. Asignar TODOS los permisos al "Administrador" (ID 1)
        $todosLosPermisos = Permiso::all();
        foreach ($todosLosPermisos as $permiso) {
            RolPermiso::firstOrCreate([
                'rol_id' => $rolAdmin->id,
                'permiso_id' => $permiso->id
            ]);
        }

        // 5. Asignar permisos específicos al "Asesor de Ventas"
        // Le daremos acceso a usuarios y a todo el módulo operativo
        $permisosAsesor = [
            'acceso_usuarios',
            'acceso_propietarios',
            'acceso_manzanos',
            'acceso_propiedades',
            'acceso_clientes',
            'acceso_ventas',
            'acceso_historial_ventas'
        ];

        foreach ($permisosAsesor as $nombrePermiso) {
            $permiso = Permiso::where('nombre', $nombrePermiso)->first();
            if ($permiso) {
                RolPermiso::firstOrCreate([
                    'rol_id' => $rolAsesor->id,
                    'permiso_id' => $permiso->id
                ]);
            }
        }
    }
}