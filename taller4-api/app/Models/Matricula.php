<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Matricula extends Model
{
    public function pagos()
    {
        return $this->hasMany(Pago::class);
    }

    protected $fillable = ['estudiante_id', 'curso_id', 'periodo', 'estado', 'fecha_matricula'];

    protected function casts(): array
    {
        return ['fecha_matricula' => 'datetime'];
    }

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }
}
