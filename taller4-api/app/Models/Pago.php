<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pago extends Model
{
    protected $fillable = ['matricula_id', 'valor', 'moneda', 'estado', 'proveedor', 'referencia', 'procesado_at'];

    protected function casts(): array
    {
        return ['valor' => 'decimal:2', 'procesado_at' => 'datetime'];
    }

    public function matricula()
    {
        return $this->belongsTo(Matricula::class);
    }

    public function transacciones()
    {
        return $this->hasMany(PagoTransaccion::class);
    }
}
