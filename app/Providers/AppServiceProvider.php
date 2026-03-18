<?php

namespace App\Providers;

use App\Models\ExerciseList;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Payment;
use App\Models\TurmaClass;
use App\Policies\ClassPolicy;
use App\Policies\ExerciseListPolicy;
use App\Policies\LessonPolicy;
use App\Policies\MaterialPolicy;
use App\Policies\PaymentPolicy;
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
        Gate::policy(TurmaClass::class, ClassPolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);
        Gate::policy(Material::class, MaterialPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(ExerciseList::class, ExerciseListPolicy::class);
    }
}
