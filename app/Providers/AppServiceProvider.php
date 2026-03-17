<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(\App\Models\TurmaClass::class, \App\Policies\ClassPolicy::class);
        Gate::policy(\App\Models\Lesson::class, \App\Policies\LessonPolicy::class);
        Gate::policy(\App\Models\Material::class, \App\Policies\MaterialPolicy::class);
        Gate::policy(\App\Models\Payment::class, \App\Policies\PaymentPolicy::class);
        Gate::policy(\App\Models\ExerciseList::class, \App\Policies\ExerciseListPolicy::class);
    }
}
