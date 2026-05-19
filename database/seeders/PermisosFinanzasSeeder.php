<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\RolPermiso;
use App\Models\RolPermisoUsuario;

class PermisosFinanzasSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Crear los dos permisos nuevos del módulo Finanzas
        $nuevosPermisos = [
            [
                'nombre'      => 'acceso_ingresos',
                'descripcion' => 'Acceso completo al módulo de Ingresos (flujo de caja)',
            ],
            [
                'nombre'      => 'acceso_egresos',
                'descripcion' => 'Acceso completo al módulo de Egresos (flujo de caja)',
            ],
        ];

        $permisosCreados = [];
        foreach ($nuevosPermisos as $datos) {
            $permisosCreados[] = Permiso::firstOrCreate(
                ['nombre' => $datos['nombre']],
                $datos
            );
        }

        $this->command->info('Permisos creados: acceso_ingresos, acceso_egresos');

        // 2. Asignar los permisos al rol Administrador en rol_permiso
        $rolAdmin = Rol::where('nombre', 'Administrador')->firstOrFail();

        $rolPermisosNuevos = [];
        foreach ($permisosCreados as $permiso) {
            $rolPermisosNuevos[] = RolPermiso::firstOrCreate([
                'rol_id'     => $rolAdmin->id,
                'permiso_id' => $permiso->id,
            ]);
        }

        $this->command->info('Permisos asignados al rol Administrador.');

        // 3. Propagar los nuevos rol_permiso a todos los usuarios que ya son Administradores
        //    (usuarios que tienen al menos una asignación del rol Administrador)
        $rolPermisoIdsAdmin = RolPermiso::where('rol_id', $rolAdmin->id)->pluck('id');

        $usuariosAdmin = RolPermisoUsuario::whereIn('rol_permiso_id', $rolPermisoIdsAdmin)
            ->distinct()
            ->pluck('user_id');

        $asignados = 0;
        foreach ($usuariosAdmin as $userId) {
            foreach ($rolPermisosNuevos as $rolPermiso) {
                $created = RolPermisoUsuario::firstOrCreate([
                    'user_id'       => $userId,
                    'rol_permiso_id' => $rolPermiso->id,
                ]);
                if ($created->wasRecentlyCreated) {
                    $asignados++;
                }
            }
        }

        $this->command->info("Permisos propagados a {$usuariosAdmin->count()} usuario(s) administrador(es). ({$asignados} asignaciones nuevas)");
    }
}
