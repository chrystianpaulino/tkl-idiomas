<?php

namespace Database\Seeders;

use App\Models\LessonPackage;
use App\Models\School;
use App\Models\TurmaClass;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Default school (tenant). Idempotent: the migration
        // 2026_03_16_200002_seed_default_school_and_populate_school_ids
        // may have already created this row.
        $school = School::firstOrCreate(
            ['slug' => 'tkl-idiomas'],
            [
                'name' => 'TKL Idiomas',
                'email' => 'contato@tkl.com',
                'active' => true,
            ]
        );

        // After Wave 9 (invite flow) the User model implements MustVerifyEmail,
        // so the `verified` middleware now actually blocks unverified users.
        // Seed accounts are convenience accounts for local development -- they
        // bypass the invite flow and must therefore be flagged as already
        // verified, otherwise every dev session would 302 to /verify-email.

        // Super admin (platform-level, no school)
        $superAdmin = new User;
        $superAdmin->name = 'Super Admin';
        $superAdmin->email = 'super@tkl.com';
        $superAdmin->password = Hash::make('password');
        $superAdmin->role = 'super_admin';
        $superAdmin->email_verified_at = now();
        $superAdmin->save();

        // School admin (tenant-level, belongs to the default school)
        $admin = new User;
        $admin->name = 'Admin TKL';
        $admin->email = 'admin@tkl.com';
        $admin->password = Hash::make('password');
        $admin->role = 'school_admin';
        $admin->school_id = $school->id;
        $admin->email_verified_at = now();
        $admin->save();

        // 3 Professors
        $professors = collect();
        foreach (['Ana Silva', 'Bruno Costa', 'Carla Mendes'] as $name) {
            $professor = new User;
            $professor->name = $name;
            $professor->email = strtolower(str_replace(' ', '.', $name)).'@tkl.com';
            $professor->password = Hash::make('password');
            $professor->role = 'professor';
            $professor->school_id = $school->id;
            $professor->email_verified_at = now();
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
            $student = new User;
            $student->name = $name;
            $student->email = strtolower(str_replace(' ', '.', $name)).'@example.com';
            $student->password = Hash::make('password');
            $student->role = 'aluno';
            $student->school_id = $school->id;
            $student->email_verified_at = now();
            $student->save();

            // 20-lesson package for each student (R$ 1.100,00 -- R$ 55 per lesson)
            $package = new LessonPackage;
            $package->student_id = $student->id;
            $package->total_lessons = 20;
            $package->price = 1100.00;
            $package->currency = 'BRL';
            $package->purchased_at = now();
            $package->expires_at = now()->addYear();
            $package->school_id = $school->id;
            $package->save();

            $students->push($student);
        }

        // 1 Class: "Inglês Básico" taught by first professor, with 5 enrolled students
        $turma = new TurmaClass;
        $turma->name = 'Inglês Básico';
        $turma->professor_id = $professors->first()->id;
        $turma->description = 'Curso de inglês para iniciantes — nível A1/A2.';
        $turma->school_id = $school->id;
        $turma->save();

        // Enroll first 5 students
        $turma->students()->attach($students->take(5)->pluck('id')->toArray());

        $this->command->info('✓ Seed completo!');
        $this->command->info('  Super:     super@tkl.com / password');
        $this->command->info('  Admin:     admin@tkl.com / password');
        $this->command->info('  Professor: ana.silva@tkl.com / password');
        $this->command->info('  Aluno:     alice.ferreira@example.com / password');
    }
}
