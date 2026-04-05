<?php

namespace App\Actions\Business;

use App\Models\ActionLog;
use App\Models\Branch;
use App\Services\PromptBuilder\PromptBuilder;

class UpdateWorkingHours
{
    public function execute(string $branchId, array $workingHours, string $triggeredBy = 'dashboard', ?string $sessionToken = null): Branch
    {
        $branch = Branch::findOrFail($branchId);
        $oldHours = $branch->working_hours;

        $branch->update(['working_hours' => $workingHours]);

        ActionLog::create([
            'business_id' => $branch->business_id,
            'branch_id' => $branch->id,
            'session_token' => $sessionToken,
            'action_name' => 'update_working_hours',
            'triggered_by' => $triggeredBy,
            'input_data' => ['branch_id' => $branchId, 'working_hours' => $workingHours],
            'output_data' => ['old' => $oldHours, 'new' => $workingHours],
            'success' => true,
            'triggered_at' => now(),
        ]);

        PromptBuilder::rebuildForBusiness($branch->business_id);

        return $branch->fresh();
    }
}
