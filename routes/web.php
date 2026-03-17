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
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard (all authenticated users)
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Profile (Breeze default)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin + Professor — class/lesson/material management
    // NOTE: These routes are placed BEFORE /classes/{class} to avoid the wildcard
    // matching "create" as a class ID.
    Route::middleware('role:admin,professor')->group(function () {
        Route::get('/classes/create', [ClassController::class, 'create'])->name('classes.create');
        Route::post('/classes', [ClassController::class, 'store'])->name('classes.store');
        Route::get('/classes/{class}/edit', [ClassController::class, 'edit'])->name('classes.edit');
        Route::put('/classes/{class}', [ClassController::class, 'update'])->name('classes.update');
        Route::delete('/classes/{class}', [ClassController::class, 'destroy'])->name('classes.destroy');

        // Lesson registration
        Route::get('/classes/{class}/lessons/create', [LessonController::class, 'create'])->name('classes.lessons.create');
        Route::post('/classes/{class}/lessons', [LessonController::class, 'store'])->name('classes.lessons.store');
        Route::delete('/classes/{class}/lessons/{lesson}', [LessonController::class, 'destroy'])->name('classes.lessons.destroy');

        // Material upload
        Route::get('/classes/{class}/materials/create', [MaterialController::class, 'create'])->name('classes.materials.create');
        Route::post('/classes/{class}/materials', [MaterialController::class, 'store'])->name('classes.materials.store');
        Route::delete('/classes/{class}/materials/{material}', [MaterialController::class, 'destroy'])->name('classes.materials.destroy');

        // Exercise list management (create must be before {exerciseList} wildcard)
        Route::get('/classes/{class}/exercise-lists/create', [ExerciseListController::class, 'create'])->name('classes.exercise-lists.create');
        Route::post('/classes/{class}/exercise-lists', [ExerciseListController::class, 'store'])->name('classes.exercise-lists.store');
        Route::delete('/classes/{class}/exercise-lists/{exerciseList}', [ExerciseListController::class, 'destroy'])->name('classes.exercise-lists.destroy');
    });

    // Classes — viewable by all authenticated users
    Route::get('/classes', [ClassController::class, 'index'])->name('classes.index');
    Route::get('/classes/{class}', [ClassController::class, 'show'])->name('classes.show');

    // Nested: Lessons per class (all authenticated can view)
    Route::get('/classes/{class}/lessons', [LessonController::class, 'index'])->name('classes.lessons.index');

    // Nested: Materials per class (all authenticated can view/download)
    Route::get('/classes/{class}/materials', [MaterialController::class, 'index'])->name('classes.materials.index');
    Route::get('/materials/{material}/download', [MaterialController::class, 'download'])->name('materials.download');

    // Exercise lists — viewable by all authenticated enrolled users
    Route::get('/classes/{class}/exercise-lists', [ExerciseListController::class, 'index'])->name('classes.exercise-lists.index');
    Route::get('/classes/{class}/exercise-lists/{exerciseList}', [ExerciseListController::class, 'show'])->name('classes.exercise-lists.show');

    // Student submission
    Route::get('/classes/{class}/exercise-lists/{exerciseList}/submit', [ExerciseSubmissionController::class, 'create'])->name('classes.exercise-lists.submit');
    Route::post('/classes/{class}/exercise-lists/{exerciseList}/submit', [ExerciseSubmissionController::class, 'store'])->name('classes.exercise-lists.submit.store');

    // Admin only
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        // User management
        Route::resource('users', UserController::class);

        // Package management per student
        Route::get('/users/{student}/packages', [LessonPackageController::class, 'index'])->name('users.packages.index');
        Route::post('/users/{student}/packages', [LessonPackageController::class, 'store'])->name('users.packages.store');
        Route::delete('/users/{student}/packages/{package}', [LessonPackageController::class, 'destroy'])->name('users.packages.destroy');

        // Enrollment management
        Route::post('/classes/{class}/enroll', [EnrollmentController::class, 'store'])->name('classes.enroll');
        Route::delete('/classes/{class}/enroll/{student}', [EnrollmentController::class, 'destroy'])->name('classes.unenroll');

        // Payment management (admin only — registering payments for student packages)
        Route::get('/users/{student}/payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('/users/{student}/packages/{package}/payments', [PaymentController::class, 'store'])->name('payments.store')->scopeBindings();
    });
});

require __DIR__.'/auth.php';
