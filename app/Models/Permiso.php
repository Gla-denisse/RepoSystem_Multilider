<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    use HasFactory;
    protected $table = 'permisos';
    protected $fillable = ['nombre', 'descripcion', 'estado'];

    public function roles() {
        return $this->belongsToMany(Rol::class, 'rol_permiso', 'permiso_id', 'rol_id')
                    ->withPivot('id')
                    ->withTimestamps();
    }
}
