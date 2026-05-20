<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\RolPermiso;
use Illuminate\Database\Seeder;

class PermisoCorreoMasivoSeeder extends Seeder
{
    public function run(): void
    {
        $permiso = Permiso::firstOrCreate(
            ['nombre' => 'acceso_correo_masivo'],
            ['descripcion' => 'Acceso completo al módulo de Correo Masivo']
        );

        // Asignar automáticamente al rol Administrador
        $admin = Rol::where('nombre', 'Administrador')->first();
        if ($admin) {
            RolPermiso::firstOrCreate([
                'rol_id'     => $admin->id,
                'permiso_id' => $permiso->id,
            ]);
        }
    }
}
