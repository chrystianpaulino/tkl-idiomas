<?php

namespace App\Http\Controllers;

use App\Actions\GetDashboardStatsAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Single-action controller for the platform (super_admin) dashboard.
 *
 * Delegates stat assembly to GetDashboardStatsAction::superAdminStats().
 */
class PlatformDashboardController extends Controller
{
    public function __invoke(Request $request, GetDashboardStatsAction $action): Response
    {
        return Inertia::render('Platform/Dashboard', [
            'stats' => $action->execute($request->user()),
        ]);
    }
}
