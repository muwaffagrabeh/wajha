<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $businesses = Business::where('user_id', $request->user()->id)
            ->with(['sectorType.sector', 'branches'])
            ->get()
            ->map(fn($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'sector' => $b->sectorType->sector->label,
                'sector_type' => $b->sectorType->label,
                'status' => $b->status,
                'branches' => $b->branches->map(fn($br) => [
                    'id' => $br->id,
                    'name' => $br->name,
                    'city' => $br->city,
                    'district' => $br->district,
                    'working_hours' => $br->working_hours,
                ]),
            ]);

        return response()->json($businesses);
    }
}
