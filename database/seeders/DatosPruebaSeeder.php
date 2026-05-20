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
use App\Models\Ciudad;
use App\Models\Distrito;
use App\Models\SectorUrbano;
use App\Models\Caracteristica;
use App\Models\Ubicacion;
use App\Models\Propiedad;
use App\Models\ImagenPropiedad;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatosPruebaSeeder extends Seeder
{
    public function run(): void
    {
        // ... (rest of the run method until Propiedad 1)

        // ==========================================
        // 1. POBLAR ASESORES (Vinculados a los usuarios)
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
        // 2. POBLAR CLIENTES
        // ==========================================
        $rolCliente = Rol::where('nombre', 'Cliente')->first();
        $permisosCliente = RolPermiso::where('rol_id', $rolCliente->id)->get();

        $clientesDemo = [
            ['ci' => '8989891', 'exp' => 'SC', 'nombre' => 'Roberto Carlos Diaz', 'correo' => 'roberto@gmail.com', 'tel' => '71122334'],
            ['ci' => '5454542', 'exp' => 'CB', 'nombre' => 'Ana Maria Salvatierra', 'correo' => 'ana.maria@hotmail.com', 'tel' => '72233445']
        ];

        foreach ($clientesDemo as $c) {
            $userCliente = User::firstOrCreate(
                ['correo' => $c['correo']],
                ['nombre' => $c['nombre'], 'password' => Hash::make($c['ci']), 'estado' => true]
            );

            foreach ($permisosCliente as $permiso) {
                RolPermisoUsuario::firstOrCreate(['user_id' => $userCliente->id, 'rol_permiso_id' => $permiso->id]);
            }

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
            ['nombre_empresa' => 'Inversiones Inmobiliarias S.A.'],
            [
                'tipo' => 'empresa',
                'nombre_completo' => 'Juan Carlos Méndez',
                'nombre_empresa' => 'Inversiones Inmobiliarias S.A.',
                'nit' => '10203040001',
                'telefono' => '33445566', 'correo' => 'gerencia@inversiones.com.bo',
                'direccion' => 'Edificio Empresarial, Piso 5', 'estado' => true
            ]
        );

        $propietario2 = Propietario::firstOrCreate(
            ['ci' => '40302010'],
            [
                'tipo' => 'persona_natural',
                'ci' => '40302010',
                'lugar_expedicion' => 'LP', 'nombre_completo' => 'Mario Alejandro Flores',
                'telefono' => '22334455', 'correo' => 'mflores@elbosque.com',
                'direccion' => 'Av. Las Americas #123', 'estado' => true
            ]
        );

        // ==========================================
        // 4. POBLAR CIUDADES, DISTRITOS, SECTORES URBANOS Y CARACTERÍSTICAS
        // ==========================================
        $ciudadWarnes = Ciudad::firstOrCreate(['nombre' => 'Warnes'], ['departamento' => 'Santa Cruz']);
        $ciudadMontero = Ciudad::firstOrCreate(['nombre' => 'Montero'], ['departamento' => 'Santa Cruz']);

        $distritoSatelite = Distrito::firstOrCreate(
            ['nombre' => 'Satélite Norte', 'ciudad_id' => $ciudadWarnes->id],
            ['estado' => true]
        );
        $distritoCentroMontero = Distrito::firstOrCreate(
            ['nombre' => 'Zona Oeste', 'ciudad_id' => $ciudadMontero->id],
            ['estado' => true]
        );

        $sectorJuanPablo = SectorUrbano::firstOrCreate(
            ['nombre' => 'Juan Pablo II', 'distrito_id' => $distritoSatelite->id],
            ['tipo' => 'Barrio', 'estado' => true]
        );
        $sectorVillaEsterita = SectorUrbano::firstOrCreate(
            ['nombre' => 'Villa Esterita', 'distrito_id' => $distritoCentroMontero->id],
            ['tipo' => 'Urbanización', 'estado' => true]
        );

        $cGas = Caracteristica::firstOrCreate(['nombre' => 'Gas Domiciliario'], ['tipo' => 'Servicios']);
        $cTransporte = Caracteristica::firstOrCreate(['nombre' => 'Transporte Público Cercano'], ['tipo' => 'Entorno']);
        $cColegio = Caracteristica::firstOrCreate(['nombre' => 'Colegios Cercanos'], ['tipo' => 'Entorno']);
        $cAreasVerdes = Caracteristica::firstOrCreate(['nombre' => 'Áreas Verdes'], ['tipo' => 'Entorno']);
        $cLavanderia = Caracteristica::firstOrCreate(['nombre' => 'Área de Lavandería'], ['tipo' => 'Interna']);

        // ==========================================
        // 5. POBLAR PROPIEDADES (Con nueva estructura)
        // ==========================================
        
        // Propiedad 1: Casa en Warnes (Con Ubicación simulada)
        $ubi1 = Ubicacion::firstOrCreate(
            ['latitud' => '-17.514333'],
            [
                'referencia' => 'A 1½ cuadra de la carretera Warnes-SCZ',
                'url_maps' => 'https://www.google.com/maps/search/?api=1&query=$-17.338062,-63.245930',
                'longitud' => '-63.167232'
            ]
        );

        $casaWarnes = Propiedad::where('ubicacion_id', $ubi1->id)->first();
        if (!$casaWarnes) {
            $casaWarnes = Propiedad::create([
                'sector_urbano_id' => $sectorJuanPablo->id,
                'ubicacion_id' => $ubi1->id,
                'tipo' => 'Casa',
                'precio_venta' => 47000.00,
                'moneda' => 'USD',
                'superficie_m2' => 300.00,
                'superficie_construida_m2' => 78.00,
                'habitaciones' => 3,
                'banos' => 2,
                'es_esquina' => false,
                'direccion' => 'Calle Principal Sur',
                'estado' => 'Disponible',
                'activo' => true,
            ]);
            $casaWarnes->codigo = Propiedad::siguienteCodigo();
            $casaWarnes->save();
        }
        $casaWarnes->propietarios()->sync([$propietario1->id]);
        $casaWarnes->caracteristicas()->sync([$cGas->id, $cLavanderia->id, $cTransporte->id]);

        // Propiedad 2: Lote en Montero
        $ubi2 = Ubicacion::firstOrCreate(
            ['latitud' => '-17.339123'],
            [
                'referencia' => 'Terreno en esquina, Urb. Villa Esterita',
                'url_maps' => 'https://www.google.com/maps/search/?api=1&query=$-17.339123,-63.246111',
                'longitud' => '-63.246111'
            ]
        );

        $loteMontero = Propiedad::where('ubicacion_id', $ubi2->id)->first();
        if (!$loteMontero) {
            $loteMontero = Propiedad::create([
                'sector_urbano_id' => $sectorVillaEsterita->id,
                'ubicacion_id' => $ubi2->id,
                'tipo' => 'Lote',
                'precio_venta' => 13500.00,
                'moneda' => 'USD',
                'superficie_m2' => 450.00,
                'frente_mts' => 15.00,
                'fondo_mts' => 30.00,
                'habitaciones' => 0,
                'banos' => 0,
                'es_esquina' => true,
                'direccion' => 'Carretera a Saavedra, Esquina Av. Principal',
                'nro_lote' => '1',
                'colinda_norte' => 'Lote 2',
                'colinda_este' => 'Avenida Principal',
                'estado' => 'Disponible',
                'activo' => true,
            ]);
            $loteMontero->codigo = Propiedad::siguienteCodigo();
            $loteMontero->save();
        }
        $loteMontero->propietarios()->sync([$propietario2->id]);
        $loteMontero->caracteristicas()->sync([$cTransporte->id, $cAreasVerdes->id, $cColegio->id]);

        // ==========================================
        // 6. POBLAR IMÁGENES (Vincular con el servidor)
        // ==========================================
        $this->crearImagenesPrueba($casaWarnes);
        $this->crearImagenesPrueba($loteMontero);
    }

    /**
     * Crea imágenes de prueba descargándolas de un servicio placeholder
     * y guardándolas en el storage del servidor.
     */
    private function crearImagenesPrueba(Propiedad $propiedad, $cantidad = 3)
    {
        // Solo crear si la propiedad no tiene imágenes
        if ($propiedad->imagenes()->count() > 0) {
            return;
        }

        $folder = "propiedades/{$propiedad->id}";
        
        // Asegurar que el directorio existe
        if (!Storage::disk('public')->exists($folder)) {
            Storage::disk('public')->makeDirectory($folder);
        }

        for ($i = 0; $i < $cantidad; $i++) {
            try {
                // Descargar una imagen aleatoria (casa o terreno según el tipo)
                $keywords = $propiedad->tipo === 'Casa' ? 'house,architecture' : 'land,field';
                $imageUrl = "https://loremflickr.com/800/600/{$keywords}?lock=" . ($propiedad->id + $i);
                
                $imageContent = file_get_contents($imageUrl);
                
                if ($imageContent) {
                    $fileName = Str::random(40) . '.jpg';
                    $path = "{$folder}/{$fileName}";
                    
                    Storage::disk('public')->put($path, $imageContent);

                    ImagenPropiedad::create([
                        'propiedad_id' => $propiedad->id,
                        'url' => $path,
                        'es_principal' => ($i === 0) // La primera es la principal
                    ]);
                }
            } catch (\Exception $e) {
                // Si falla la descarga (ej. sin internet), ignorar
                logger()->error("Error al descargar imagen para propiedad {$propiedad->id}: " . $e->getMessage());
            }
        }
    }
}