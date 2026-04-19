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
            ['descripcion' => 'Acceso limitado a clientes y consultas']
        );

        // 2. Definimos los permisos exactos del sistema hasta ahora
        $permisos = [
            ['nombre' => 'ver_usuarios', 'descripcion' => 'Ver el listado de usuarios'],
            ['nombre' => 'ver_roles', 'descripcion' => 'Ver el listado de roles'],
            ['nombre' => 'ver_permisos', 'descripcion' => 'Ver el listado de permisos'],
            ['nombre' => 'crear_usuarios', 'descripcion' => 'Permite crear nuevos usuarios'],
        ];

        // 3. Insertamos los permisos en la base de datos
        foreach ($permisos as $p) {
            Permiso::firstOrCreate(['nombre' => $p['nombre']], $p);
        }

        // 4. Asignar TODOS los permisos al "Administrador"
        $todosLosPermisos = Permiso::all();
        foreach ($todosLosPermisos as $permiso) {
            RolPermiso::firstOrCreate([
                'rol_id' => $rolAdmin->id,
                'permiso_id' => $permiso->id
            ]);
        }

        // 5. Asignar permisos específicos al "Asesor de Ventas"
        // Le daremos solo acceso a ver usuarios para que puedan entrar al menú
        $permisoVerUsuarios = Permiso::where('nombre', 'ver_usuarios')->first();
        if ($permisoVerUsuarios) {
            RolPermiso::firstOrCreate([
                'rol_id' => $rolAsesor->id,
                'permiso_id' => $permisoVerUsuarios->id
            ]);
        }
    }
}