<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentPrompt extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'prompt_text',
        'tools_snapshot',
        'validator_rules',
        'gateway_routes',
        'version',
        'built_at',
        'built_by',
    ];

    protected function casts(): array
    {
        return [
            'tools_snapshot' => 'array',
            'validator_rules' => 'array',
            'gateway_routes' => 'array',
            'built_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
