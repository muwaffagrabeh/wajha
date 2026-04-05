<?php

namespace App\Actions\Services;

use App\Models\ActionLog;
use App\Models\ServiceItem;
use App\Services\PromptBuilder\PromptBuilder;

class UpdateService
{
    public function execute(string $serviceId, array $data, string $triggeredBy = 'dashboard', ?string $sessionToken = null): ServiceItem
    {
        $service = ServiceItem::findOrFail($serviceId);
        $oldData = $service->toArray();

        $service->update(array_filter($data, fn($v) => $v !== null));

        ActionLog::create([
            'business_id' => $service->business_id,
            'session_token' => $sessionToken,
            'action_name' => 'update_service',
            'triggered_by' => $triggeredBy,
            'input_data' => ['service_id' => $serviceId, 'changes' => $data],
            'output_data' => ['old' => $oldData, 'new' => $service->fresh()->toArray()],
            'success' => true,
            'triggered_at' => now(),
        ]);

        PromptBuilder::rebuildForBusiness($service->business_id);

        return $service->fresh();
    }
}
