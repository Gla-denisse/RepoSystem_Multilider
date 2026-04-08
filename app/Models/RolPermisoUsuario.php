<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolPermisoUsuario extends Model
{
    use HasFactory;
    protected $table = 'rol_permiso_usuario';
    // Cambiamos usuario_id por user_id
    protected $fillable = ['user_id', 'rol_permiso_id'];

    public function user() {
        // Cambiamos la relación para que apunte al modelo User
        return $this->belongsTo(User::class, 'user_id');
    }

    public function rolPermiso() {
        return $this->belongsTo(RolPermiso::class);
    }
}
