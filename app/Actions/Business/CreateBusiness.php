<?php

namespace App\Actions\Business;

use App\Models\ActionLog;
use App\Models\Business;
use App\Models\SectorType;

class CreateBusiness
{
    public function execute(array $data, string $triggeredBy = 'dashboard', ?string $sessionToken = null): Business
    {
        $sectorType = SectorType::findOrFail($data['sector_type_id']);

        $business = Business::create([
            'user_id' => $data['user_id'] ?? null,
            'sector_type_id' => $data['sector_type_id'],
            'name' => $data['name'],
            'name_en' => $data['name_en'] ?? null,
            'active_patterns' => $sectorType->default_patterns,
            'active_layers' => $sectorType->default_layers,
            'agent_name' => 'مساعد ' . $data['name'],
            'agent_tone' => $data['agent_tone'] ?? 'ودود ومهني',
            'agent_dialect' => $data['agent_dialect'] ?? 'saudi',
        ]);

        ActionLog::create([
            'business_id' => $business->id,
            'session_token' => $sessionToken,
            'action_name' => 'create_business',
            'triggered_by' => $triggeredBy,
            'input_data' => $data,
            'output_data' => ['business_id' => $business->id],
            'success' => true,
            'triggered_at' => now(),
        ]);

        return $business;
    }
}
