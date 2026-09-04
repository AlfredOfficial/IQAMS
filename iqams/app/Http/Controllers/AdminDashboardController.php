<?php

namespace App\Http\Controllers;

use App\Services\AdminDashboardData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(AdminDashboardData $dashboard): View
    {
        return view('dashboard', [
            'dashboardData' => $dashboard->build(includeFilters: true),
        ]);
    }

    public function realtime(Request $request, AdminDashboardData $dashboard): JsonResponse
    {
        $validated = $request->validate([
            'cursor' => ['nullable', 'date'],
        ]);
        $cursor = isset($validated['cursor'])
            ? Carbon::parse($validated['cursor'], config('app.timezone'))
            : null;

        return response()->json($dashboard->build($cursor, includeFilters: $cursor === null));
    }

    public function delta(Request $request, AdminDashboardData $dashboard): JsonResponse
    {
        $validated = $request->validate([
            'cursor' => ['nullable', 'date'],
        ]);
        $cursor = isset($validated['cursor'])
            ? Carbon::parse($validated['cursor'], config('app.timezone'))
            : null;

        return response()->json($dashboard->buildDelta($cursor));
    }

    public function analytics(AdminDashboardData $dashboard): JsonResponse
    {
        return response()->json($dashboard->analytics());
    }
}
