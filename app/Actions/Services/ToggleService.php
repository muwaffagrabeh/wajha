<?php

namespace App\Actions\Services;

use App\Models\ActionLog;
use App\Models\ServiceItem;
use App\Services\PromptBuilder\PromptBuilder;

class ToggleService
{
    public function execute(string $serviceId, bool $active, string $triggeredBy = 'dashboard', ?string $sessionToken = null): ServiceItem
    {
        $service = ServiceItem::findOrFail($serviceId);
        $service->update(['status' => $active ? 'active' : 'inactive']);

        ActionLog::create([
            'business_id' => $service->business_id,
            'session_token' => $sessionToken,
            'action_name' => 'toggle_service',
            'triggered_by' => $triggeredBy,
            'input_data' => ['service_id' => $serviceId, 'active' => $active],
            'output_data' => ['status' => $service->status],
            'success' => true,
            'triggered_at' => now(),
        ]);

        PromptBuilder::rebuildForBusiness($service->business_id);

        return $service;
    }
}
