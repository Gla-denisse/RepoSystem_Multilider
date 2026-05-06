<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MiEmpresa;

class MiEmpresaSeeder extends Seeder
{
    public function run(): void
    {
        // Usamos updateOrCreate para evitar duplicados si se ejecuta varias veces
        MiEmpresa::updateOrCreate(
            ['id' => 1], // Buscamos el ID 1
            [
                'nombre' => 'Multilider System',
                'eslogan' => 'Tu mejor aliado en bienes raíces',
                'descripcion_nosotros' => 'Somos una empresa líder en el sector inmobiliario con años de experiencia brindando soluciones habitacionales y de inversión en las mejores zonas del país.',
                'mision' => 'Brindar el mejor servicio de intermediación inmobiliaria, superando las expectativas de nuestros clientes con transparencia y profesionalismo.',
                'vision' => 'Ser la inmobiliaria número uno del país, reconocida por nuestra ética, innovación y calidad humana.',
                'valores' => "• Honestidad\n• Compromiso\n• Excelencia\n• Transparencia",
                'direccion' => 'Calle Principal #123, Santa Cruz, Bolivia',
                'telefono' => '+591 3 3456789',
                'whatsapp' => '59170000000',
                'email' => 'contacto@multilider.com',
                'facebook' => 'https://facebook.com/multilider',
                'instagram' => 'https://instagram.com/multilider',
                'tiktok' => 'https://tiktok.com/@multilider',
                'youtube' => 'https://youtube.com/multilider',
                'color_primario' => '#1e40af', 
                'color_secundario' => '#ffffff',
                
                // Datos del Hero Slider (Placeholders)
                'hero_title_1' => 'Encuentra el Lote de tus Sueños',
                'hero_subtitle_1' => 'Contamos con terrenos en las mejores zonas de expansión urbana.',
                'hero_title_2' => 'Tu Futuro Hogar Comienza Aquí',
                'hero_subtitle_2' => 'Casas modernas con financiamiento bancario disponible.',
                'hero_title_3' => 'Inversión Segura y Rentable',
                'hero_subtitle_3' => 'Asegura tu patrimonio con expertos en bienes raíces.',
            ]
        );
    }
}