<?php

namespace App\Http\Controllers;

use App\Actions\GetDashboardStatsAction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GetDashboardStatsAction $action): Response
    {
        return Inertia::render('Dashboard', [
            'stats' => $action->execute($request->user()),
        ]);
    }
}
