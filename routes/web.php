<?php

use App\Http\Controllers\ClassController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ExerciseListController;
use App\Http\Controllers\ExerciseSubmissionController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LessonPackageController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SchoolController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // ── Compartilhadas — todos os papéis autenticados ────────────────────────

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ── Professor + Admin — gestão de turmas, aulas e materiais ─────────────
    // IMPORTANTE: Este grupo é registado ANTES de /classes/{class} para que
    // /classes/create não seja capturado pelo wildcard como se fosse um ID de turma.

    Route::middleware('role:admin,professor')->group(function () {
        Route::get('/classes/create', [ClassController::class, 'create'])->name('classes.create');
        Route::post('/classes', [ClassController::class, 'store'])->name('classes.store');
        Route::get('/classes/{class}/edit', [ClassController::class, 'edit'])->name('classes.edit');
        Route::put('/classes/{class}', [ClassController::class, 'update'])->name('classes.update');
        Route::delete('/classes/{class}', [ClassController::class, 'destroy'])->name('classes.destroy');

        // Aulas
        Route::get('/classes/{class}/lessons/create', [LessonController::class, 'create'])->name('classes.lessons.create');
        Route::post('/classes/{class}/lessons', [LessonController::class, 'store'])->name('classes.lessons.store');
        Route::delete('/classes/{class}/lessons/{lesson}', [LessonController::class, 'destroy'])->name('classes.lessons.destroy');

        // Materiais
        Route::get('/classes/{class}/materials/create', [MaterialController::class, 'create'])->name('classes.materials.create');
        Route::post('/classes/{class}/materials', [MaterialController::class, 'store'])->name('classes.materials.store');
        Route::delete('/classes/{class}/materials/{material}', [MaterialController::class, 'destroy'])->name('classes.materials.destroy');

        // Listas de exercícios (create deve vir antes do wildcard {exerciseList})
        Route::get('/classes/{class}/exercise-lists/create', [ExerciseListController::class, 'create'])->name('classes.exercise-lists.create');
        Route::post('/classes/{class}/exercise-lists', [ExerciseListController::class, 'store'])->name('classes.exercise-lists.store');
        Route::delete('/classes/{class}/exercise-lists/{exerciseList}', [ExerciseListController::class, 'destroy'])->name('classes.exercise-lists.destroy');
    });

    // ── Leitura — turmas, aulas e materiais (todos os papéis) ───────────────

    Route::get('/classes', [ClassController::class, 'index'])->name('classes.index');
    Route::get('/classes/{class}', [ClassController::class, 'show'])->name('classes.show');

    Route::get('/classes/{class}/lessons', [LessonController::class, 'index'])->name('classes.lessons.index');

    Route::get('/classes/{class}/materials', [MaterialController::class, 'index'])->name('classes.materials.index');
    Route::get('/materials/{material}/download', [MaterialController::class, 'download'])->name('materials.download');

    Route::get('/classes/{class}/exercise-lists', [ExerciseListController::class, 'index'])->name('classes.exercise-lists.index');
    Route::get('/classes/{class}/exercise-lists/{exerciseList}', [ExerciseListController::class, 'show'])->name('classes.exercise-lists.show');

    // ── Aluno — submissão de listas de exercícios ────────────────────────────

    Route::get('/classes/{class}/exercise-lists/{exerciseList}/submit', [ExerciseSubmissionController::class, 'create'])->name('classes.exercise-lists.submit');
    Route::post('/classes/{class}/exercise-lists/{exerciseList}/submit', [ExerciseSubmissionController::class, 'store'])->name('classes.exercise-lists.submit.store');

    // ── Admin — gestão global da escola ─────────────────────────────────────

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        // Usuários
        Route::resource('users', UserController::class);

        // Pacotes de aulas por aluno
        Route::get('/users/{student}/packages', [LessonPackageController::class, 'index'])->name('users.packages.index');
        Route::post('/users/{student}/packages', [LessonPackageController::class, 'store'])->name('users.packages.store');
        Route::delete('/users/{student}/packages/{package}', [LessonPackageController::class, 'destroy'])->name('users.packages.destroy');

        // Matrículas
        Route::post('/classes/{class}/enroll', [EnrollmentController::class, 'store'])->name('classes.enroll');
        Route::delete('/classes/{class}/enroll/{student}', [EnrollmentController::class, 'destroy'])->name('classes.unenroll');

        // Pagamentos
        Route::get('/payments/report', [PaymentController::class, 'report'])->name('payments.report');
        Route::get('/users/{student}/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/users/{student}/packages/{package}/payments', [PaymentController::class, 'store'])->name('payments.store')->scopeBindings();

        // Escolas
        Route::resource('schools', SchoolController::class)->except(['show']);
    });
});

require __DIR__.'/auth.php';
