<?php

namespace App\Actions\Business;

use App\Models\ActionLog;
use App\Models\Branch;
use App\Services\PromptBuilder\PromptBuilder;

class CreateBranch
{
    public function execute(array $data, string $triggeredBy = 'dashboard', ?string $sessionToken = null): Branch
    {
        $branch = Branch::create([
            'business_id' => $data['business_id'],
            'name' => $data['name'] ?? 'الفرع الرئيسي',
            'city' => $data['city'],
            'district' => $data['district'] ?? null,
            'phone' => $data['phone'] ?? null,
            'working_hours' => $data['working_hours'] ?? null,
            'is_default' => true,
        ]);

        ActionLog::create([
            'business_id' => $data['business_id'],
            'branch_id' => $branch->id,
            'session_token' => $sessionToken,
            'action_name' => 'create_branch',
            'triggered_by' => $triggeredBy,
            'input_data' => $data,
            'output_data' => ['branch_id' => $branch->id],
            'success' => true,
            'triggered_at' => now(),
        ]);

        PromptBuilder::rebuildForBusiness($data['business_id']);

        return $branch;
    }
}
