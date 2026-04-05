<?php

namespace App\Actions\Business;

use App\Models\ActionLog;
use App\Models\BusinessPolicy;
use App\Services\PromptBuilder\PromptBuilder;

class UpdatePolicy
{
    public function execute(string $businessId, string $key, string $value, string $triggeredBy = 'dashboard', ?string $sessionToken = null): BusinessPolicy
    {
        $policy = BusinessPolicy::updateOrCreate(
            ['business_id' => $businessId, 'key' => $key],
            ['value' => $value]
        );

        ActionLog::create([
            'business_id' => $businessId,
            'session_token' => $sessionToken,
            'action_name' => 'update_policy',
            'triggered_by' => $triggeredBy,
            'input_data' => ['key' => $key, 'value' => $value],
            'output_data' => ['policy_id' => $policy->id],
            'success' => true,
            'triggered_at' => now(),
        ]);

        PromptBuilder::rebuildForBusiness($businessId);

        return $policy;
    }
}
