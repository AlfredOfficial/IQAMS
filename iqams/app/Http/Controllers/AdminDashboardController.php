<?php

namespace App\Http\Controllers;

use App\Services\AdminDashboardData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function analytics(Request $request, AdminDashboardData $dashboard): JsonResponse|Response
    {
        $payload = $dashboard->analytics();
        $etag = '"'.sha1((string) json_encode($payload)).'"';

        if ($request->header('If-None-Match') === $etag) {
            return response()->noContent(Response::HTTP_NOT_MODIFIED)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'private, no-cache');
        }

        return response()->json($payload)
            ->header('ETag', $etag)
            ->header('Cache-Control', 'private, no-cache');
    }
}
