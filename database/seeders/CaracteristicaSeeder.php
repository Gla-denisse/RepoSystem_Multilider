<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Caracteristica;

class CaracteristicaSeeder extends Seeder
{
    public function run(): void
    {
        $caracteristicas = [
            // --- SERVICIOS ---
            ['nombre' => 'Luz Eléctrica', 'tipo' => 'Servicios'],
            ['nombre' => 'Agua Potable', 'tipo' => 'Servicios'],
            ['nombre' => 'Gas Domiciliario', 'tipo' => 'Servicios'],
            ['nombre' => 'Internet Fibra Óptica', 'tipo' => 'Servicios'],
            ['nombre' => 'Alcantarillado', 'tipo' => 'Servicios'],
            ['nombre' => 'Alumbrado Público', 'tipo' => 'Servicios'],
            ['nombre' => 'Recolección de Basura', 'tipo' => 'Servicios'],

            // --- INTERNAS (AMENIDADES) ---
            ['nombre' => 'Piscina / Alberca', 'tipo' => 'Interna'],
            ['nombre' => 'Churrasquera / Quincho', 'tipo' => 'Interna'],
            ['nombre' => 'Aire Acondicionado', 'tipo' => 'Interna'],
            ['nombre' => 'Calefacción', 'tipo' => 'Interna'],
            ['nombre' => 'Cocina Integral', 'tipo' => 'Interna'],
            ['nombre' => 'Portón Eléctrico', 'tipo' => 'Interna'],
            ['nombre' => 'Cámaras de Seguridad', 'tipo' => 'Interna'],
            ['nombre' => 'Dependencia de Servicio', 'tipo' => 'Interna'],
            ['nombre' => 'Lavandería Techada', 'tipo' => 'Interna'],
            ['nombre' => 'Patio Amplio', 'tipo' => 'Interna'],
            ['nombre' => 'Terraza / Balcón', 'tipo' => 'Interna'],
            ['nombre' => 'Walk-in Closet', 'tipo' => 'Interna'],

            // --- ENTORNO ---
            ['nombre' => 'Pavimento / Asfalto', 'tipo' => 'Entorno'],
            ['nombre' => 'Cerca de Colegio', 'tipo' => 'Entorno'],
            ['nombre' => 'Cerca de Mercado / Súper', 'tipo' => 'Entorno'],
            ['nombre' => 'Parque Infantil Cercano', 'tipo' => 'Entorno'],
            ['nombre' => 'Transporte Público Cercano', 'tipo' => 'Entorno'],
            ['nombre' => 'Zona Comercial', 'tipo' => 'Entorno'],
            ['nombre' => 'Hospital / Clínica Cercana', 'tipo' => 'Entorno'],
            ['nombre' => 'Cerca de Universidad', 'tipo' => 'Entorno'],
            ['nombre' => 'Vigilancia 24/7 (Barrio Cerrado)', 'tipo' => 'Entorno'],
        ];

        foreach ($caracteristicas as $c) {
            Caracteristica::firstOrCreate(
                ['nombre' => $c['nombre']],
                ['tipo' => $c['tipo'], 'estado' => true]
            );
        }
    }
}
