<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\RolPermiso;

class PermisoEntregaSeeder extends Seeder
{
    public function run(): void
    {
        $permiso = Permiso::firstOrCreate(
            ['nombre' => 'acceso_entregas'],
            ['descripcion' => 'Acceso completo al módulo de Entregas de propiedades']
        );

        // Asignar al rol Administrador
        $rolAdmin = Rol::where('nombre', 'Administrador')->first();
        if ($rolAdmin) {
            RolPermiso::firstOrCreate([
                'rol_id'     => $rolAdmin->id,
                'permiso_id' => $permiso->id,
            ]);
        }
    }
}
