<?php

namespace App\Http\Controllers\Api;

use App\Actions\Services\CreateService;
use App\Actions\Services\ToggleService;
use App\Actions\Services\UpdateService;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\ServiceItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request, string $businessId): JsonResponse
    {
        $this->authorize($request, $businessId);

        $services = ServiceItem::where('business_id', $businessId)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get();

        return response()->json($services);
    }

    public function store(Request $request, string $businessId): JsonResponse
    {
        $this->authorize($request, $businessId);

        $data = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:service,product',
            'price' => 'required|numeric|min:0',
            'category' => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
            'requires_booking' => 'nullable|boolean',
            'requires_specialist' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);
        $data['business_id'] = $businessId;

        $service = (new CreateService())->execute($data, 'dashboard');

        return response()->json($service, 201);
    }

    public function update(Request $request, string $businessId, string $serviceId): JsonResponse
    {
        $this->authorize($request, $businessId);

        $data = $request->validate([
            'name' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'category' => 'nullable|string',
            'duration_minutes' => 'nullable|integer',
            'description' => 'nullable|string',
        ]);

        $service = (new UpdateService())->execute($serviceId, array_filter($data, fn($v) => $v !== null), 'dashboard');

        return response()->json($service);
    }

    public function toggle(Request $request, string $businessId, string $serviceId): JsonResponse
    {
        $this->authorize($request, $businessId);

        $data = $request->validate(['active' => 'required|boolean']);

        $service = (new ToggleService())->execute($serviceId, $data['active'], 'dashboard');

        return response()->json($service);
    }

    private function authorize(Request $request, string $businessId): void
    {
        $exists = Business::where('id', $businessId)
            ->where('user_id', $request->user()->id)
            ->exists();

        abort_unless($exists, 403, 'غير مصرح');
    }
}
