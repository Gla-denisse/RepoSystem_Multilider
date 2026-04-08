<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'roles';
    protected $fillable = ['nombre', 'descripcion', 'estado'];
    use HasFactory;

    public function permisos() {
        return $this->belongsToMany(Permiso::class, 'rol_permiso', 'rol_id', 'permiso_id')
                    ->withPivot('id')
                    ->withTimestamps();
    }
}
