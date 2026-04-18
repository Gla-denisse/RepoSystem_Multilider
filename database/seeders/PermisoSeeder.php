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
        // 1. Definimos los permisos exactos que necesita el sistema
        $permisos = [
            ['nombre' => 'ver_usuarios', 'descripcion' => 'Ver el listado de usuarios'],
            ['nombre' => 'ver_roles', 'descripcion' => 'Ver el listado de roles'],
            ['nombre' => 'ver_permisos', 'descripcion' => 'Ver el listado de permisos'],
            ['nombre' => 'crear_usuarios', 'descripcion' => 'Permite crear nuevos usuarios'],
            // Puedes agregar más aquí en el futuro (ej: editar_usuarios, eliminar_usuarios)
        ];

        // 2. Insertamos los permisos en la base de datos
        foreach ($permisos as $p) {
            Permiso::firstOrCreate(['nombre' => $p['nombre']], $p);
        }

        // 3. Asignamos TODOS estos permisos al Rol de "Administrador" (ID 1)
        $rolAdmin = Rol::find(1);
        
        if ($rolAdmin) {
            $todosLosPermisos = Permiso::all();
            foreach ($todosLosPermisos as $permiso) {
                RolPermiso::firstOrCreate([
                    'rol_id' => $rolAdmin->id,
                    'permiso_id' => $permiso->id
                ]);
            }
        }
    }
}