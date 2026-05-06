<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetodoPago extends Model
{
    use HasFactory;

    protected $table = 'metodos_pago';

    protected $fillable = [
        'nombre_metodo',
        'estado'
    ];

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'metodo_pago_id');
    }
}
