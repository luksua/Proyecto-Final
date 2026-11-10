<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Curso extends Model
{
    protected $fillable = [
        'codigo', 'nombre', 'creditos', 'periodo',
        'cupo_maximo', 'cupo_disponible', 'estado', 'docente_id',
    ];

    public function docente()
    {
        return $this->belongsTo(Docente::class);
    }

    public function matriculas()
    {
        return $this->hasMany(Matricula::class);
    }
}
