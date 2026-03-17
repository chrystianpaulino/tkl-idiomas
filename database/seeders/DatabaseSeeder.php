<?php

namespace Database\Seeders;

use App\Models\LessonPackage;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1 Admin
        $admin = new User();
        $admin->name = 'Admin TKL';
        $admin->email = 'admin@tkl.com';
        $admin->password = Hash::make('password');
        $admin->role = 'admin';
        $admin->save();

        // 3 Professors
        $professors = collect();
        foreach (['Ana Silva', 'Bruno Costa', 'Carla Mendes'] as $name) {
            $professor = new User();
            $professor->name = $name;
            $professor->email = strtolower(str_replace(' ', '.', $name)) . '@tkl.com';
            $professor->password = Hash::make('password');
            $professor->role = 'professor';
            $professor->save();
            $professors->push($professor);
        }

        // 10 Students, each with a 20-lesson package
        $students = collect();
        $studentNames = [
            'Alice Ferreira', 'Bruno Lima', 'Camila Santos', 'Diego Rocha',
            'Elena Nunes', 'Felipe Martins', 'Gabriela Souza', 'Henrique Alves',
            'Isabela Pereira', 'João Carvalho',
        ];

        foreach ($studentNames as $name) {
            $student = new User();
            $student->name = $name;
            $student->email = strtolower(str_replace(' ', '.', $name)) . '@example.com';
            $student->password = Hash::make('password');
            $student->role = 'aluno';
            $student->save();

            // 20-lesson package for each student
            $package = new LessonPackage();
            $package->student_id = $student->id;
            $package->total_lessons = 20;
            $package->used_lessons = 0;
            $package->purchased_at = now();
            $package->expires_at = now()->addYear();
            $package->save();

            $students->push($student);
        }

        // 1 Class: "Inglês Básico" taught by first professor, with 5 enrolled students
        $turma = new TurmaClass();
        $turma->name = 'Inglês Básico';
        $turma->professor_id = $professors->first()->id;
        $turma->description = 'Curso de inglês para iniciantes — nível A1/A2.';
        $turma->save();

        // Enroll first 5 students
        $turma->students()->attach($students->take(5)->pluck('id')->toArray());

        $this->command->info('✓ Seed completo!');
        $this->command->info('  Admin:     admin@tkl.com / password');
        $this->command->info('  Professor: ana.silva@tkl.com / password');
        $this->command->info('  Aluno:     alice.ferreira@example.com / password');
    }
}
