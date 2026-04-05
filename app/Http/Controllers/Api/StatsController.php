<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Monitoring\DailyHealthCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function index(Request $request, string $businessId): JsonResponse
    {
        $exists = Business::where('id', $businessId)
            ->where('user_id', $request->user()->id)
            ->exists();

        abort_unless($exists, 403);

        $health = new DailyHealthCheck($businessId);
        $stats = $health->run($request->query('date'));

        return response()->json($stats);
    }
}
