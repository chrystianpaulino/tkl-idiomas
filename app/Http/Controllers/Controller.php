<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base controller for all application controllers.
 *
 * Provides the AuthorizesRequests trait so child controllers can call
 * $this->authorize() to enforce Policy-based authorization. All policies
 * must be manually registered in AppServiceProvider::boot() via Gate::policy().
 */
abstract class Controller
{
    use AuthorizesRequests;
}
