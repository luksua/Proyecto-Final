<?php

namespace Database\Seeders;

use App\Models\Docente;
use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Datos base que usa la colección de Postman (contraseña de todos: password123).
     */
    public function run(): void
    {
        $docente = Docente::firstOrCreate(
            ['correo' => 'carlos.rojas@ut.edu.co'],
            ['nombres' => 'Carlos', 'apellidos' => 'Rojas', 'departamento' => 'Ingeniería de Sistemas']
        );

        $estudiante = Estudiante::firstOrCreate(
            ['documento' => '1000000001'],
            [
                'nombres' => 'Ana',
                'apellidos' => 'Martínez',
                'correo' => 'ana.martinez@ut.edu.co',
                'programa' => 'Ingeniería de Sistemas',
            ]
        );

        User::updateOrCreate(['email' => 'admin@ut.edu.co'], [
            'name' => 'Administrador', 'password' => 'password123', 'rol' => 'ADMIN',
        ]);

        User::updateOrCreate(['email' => 'carlos.rojas@ut.edu.co'], [
            'name' => 'Carlos Rojas', 'password' => 'password123', 'rol' => 'DOCENTE',
            'docente_id' => $docente->id,
        ]);

        User::updateOrCreate(['email' => 'ana.martinez@ut.edu.co'], [
            'name' => 'Ana Martínez', 'password' => 'password123', 'rol' => 'ESTUDIANTE',
            'estudiante_id' => $estudiante->id,
        ]);
    }
}
