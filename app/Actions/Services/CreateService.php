<?php

namespace App\Actions\Services;

use App\Models\ActionLog;
use App\Models\ServiceItem;
use App\Services\PromptBuilder\PromptBuilder;

class CreateService
{
    public function execute(array $data, string $triggeredBy = 'dashboard', ?string $sessionToken = null): ServiceItem
    {
        $service = ServiceItem::create([
            'business_id' => $data['business_id'],
            'name' => $data['name'],
            'name_en' => $data['name_en'] ?? null,
            'type' => in_array($data['type'] ?? '', ['service', 'product']) ? $data['type'] : 'service',
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'price_model' => $data['price_model'] ?? 'fixed',
            'price_unit' => $data['price_unit'] ?? null,
            'duration_minutes' => $data['duration_minutes'] ?? null,
            'requires_booking' => $data['requires_booking'] ?? false,
            'requires_specialist' => $data['requires_specialist'] ?? false,
            'deliverable' => $data['deliverable'] ?? 'in_person',
            'status' => 'active',
        ]);

        ActionLog::create([
            'business_id' => $data['business_id'],
            'session_token' => $sessionToken,
            'action_name' => 'create_service',
            'triggered_by' => $triggeredBy,
            'input_data' => $data,
            'output_data' => ['service_id' => $service->id],
            'success' => true,
            'triggered_at' => now(),
        ]);

        PromptBuilder::rebuildForBusiness($data['business_id']);

        return $service;
    }
}
