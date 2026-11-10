<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estudiante extends Model
{
    protected $fillable = ['documento', 'nombres', 'apellidos', 'correo', 'programa', 'estado'];

    public function matriculas()
    {
        return $this->hasMany(Matricula::class);
    }
}
