<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

/**
 * Main application service provider.
 *
 * IMPORTANT: Policy registration is MANUAL in this project. Laravel's auto-discovery
 * is NOT active. Every new policy MUST be explicitly registered here via Gate::policy(),
 * or any authorize()/can() call involving that model will throw an
 * InvalidArgumentException at runtime.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap application services: Vite prefetching and manual policy registration.
     *
     * When adding a new Policy, add a Gate::policy() call here. Forgetting to do so
     * will cause runtime errors on any authorization check for that model.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Manual policy registration (auto-discovery is NOT active)
        Gate::policy(\App\Models\TurmaClass::class, \App\Policies\ClassPolicy::class);
        Gate::policy(\App\Models\Lesson::class, \App\Policies\LessonPolicy::class);
        Gate::policy(\App\Models\Material::class, \App\Policies\MaterialPolicy::class);
        Gate::policy(\App\Models\Payment::class, \App\Policies\PaymentPolicy::class);
        Gate::policy(\App\Models\ExerciseList::class, \App\Policies\ExerciseListPolicy::class);
    }
}
