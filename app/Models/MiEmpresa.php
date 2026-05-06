<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MiEmpresa extends Model
{
    use HasFactory;

    protected $table = 'mi_empresa';

    protected $fillable = [
        'nombre',
        'logo',
        'hero_image_1', 'hero_title_1', 'hero_subtitle_1',
        'hero_image_2', 'hero_title_2', 'hero_subtitle_2',
        'hero_image_3', 'hero_title_3', 'hero_subtitle_3',
        'eslogan',
        'descripcion_nosotros',
        'mision',
        'vision',
        'valores',
        'direccion',
        'telefono',
        'whatsapp',
        'email',
        'facebook',
        'instagram',
        'tiktok',
        'youtube',
        'mapa_iframe',
        'color_primario',
        'color_secundario'
    ];

    // Relaciones
    public function cuentas()
    {
        return $this->hasMany(CuentaBancaria::class, 'mi_empresa_id');
    }
}
