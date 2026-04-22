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
            'acceso_propiedades'
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