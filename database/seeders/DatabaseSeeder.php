<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Rol;
use App\Models\RolPermiso;
use App\Models\RolPermisoUsuario;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Ejecutar el seeder de Roles y Permisos primero
        $this->call([
            PermisoSeeder::class,
            CaracteristicaSeeder::class,
            MiEmpresaSeeder::class,
        ]);

        // 2. Obtener los roles recién creados
        $rolAdmin = Rol::where('nombre', 'Administrador')->first();
        $rolAsesor = Rol::where('nombre', 'Asesor de Ventas')->first();

        // 3. Crear Usuarios Administradores (Contraseña: password)
        $admin1 = User::firstOrCreate(
            ['correo' => 'fernando@tecnoweb.edu'],
            ['nombre' => 'Fernando Administrador', 'password' => Hash::make('password'), 'estado' => true]
        );

        $admin2 = User::firstOrCreate(
            ['correo' => 'denisse@tecnoweb.edu'],
            ['nombre' => 'Denisse Administradora', 'password' => Hash::make('password'), 'estado' => true]
        );

        // 4. Crear Usuarios Asesores (Contraseña: password)
        $asesor1 = User::firstOrCreate(
            ['correo' => 'carlos@tecnoweb.edu'],
            ['nombre' => 'Carlos Mendoza', 'password' => Hash::make('password'), 'estado' => true]
        );

        $asesor2 = User::firstOrCreate(
            ['correo' => 'laura@tecnoweb.edu'],
            ['nombre' => 'Laura Castillo', 'password' => Hash::make('password'), 'estado' => true]
        );

        // ========================================================
        // 5. ASIGNAR LOS ACCESOS A LOS USUARIOS (rol_permiso_usuario)
        // ========================================================

        // A) Obtenemos las combinaciones que le pertenecen al Administrador
        $combinacionesAdmin = RolPermiso::where('rol_id', $rolAdmin->id)->get();
        foreach ($combinacionesAdmin as $combinacion) {
            RolPermisoUsuario::firstOrCreate(['user_id' => $admin1->id, 'rol_permiso_id' => $combinacion->id]);
            RolPermisoUsuario::firstOrCreate(['user_id' => $admin2->id, 'rol_permiso_id' => $combinacion->id]);
        }

        // B) Obtenemos las combinaciones que le pertenecen al Asesor
        $combinacionesAsesor = RolPermiso::where('rol_id', $rolAsesor->id)->get();
        foreach ($combinacionesAsesor as $combinacion) {
            RolPermisoUsuario::firstOrCreate(['user_id' => $asesor1->id, 'rol_permiso_id' => $combinacion->id]);
            RolPermisoUsuario::firstOrCreate(['user_id' => $asesor2->id, 'rol_permiso_id' => $combinacion->id]);
        }

        $this->call([
            DatosPruebaSeeder::class,
        ]);
    }
}