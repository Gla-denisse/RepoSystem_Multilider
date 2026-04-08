<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Rol;
use App\Models\Permiso;
use App\Models\RolPermiso;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Crear un Rol Admin
        $rolAdmin = Rol::create(['nombre' => 'Administrador', 'descripcion' => 'Acceso total']);

        // 2. Crear un Permiso
        $permisoCrear = Permiso::create(['nombre' => 'Crear Usuarios', 'descripcion' => 'Creación de nuevos usuarios']);

        // 3. Unir Rol y Permiso
        $rolPermiso = RolPermiso::create([
            'rol_id' => $rolAdmin->id,
            'permiso_id' => $permisoCrear->id
        ]);

        // 4. Crear 10 usuarios falsos con el Factory
        User::factory(10)->create();
    }
}
