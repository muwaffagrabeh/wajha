<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionLog extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'business_id',
        'branch_id',
        'session_token',
        'action_name',
        'triggered_by',
        'input_data',
        'output_data',
        'success',
        'error_message',
        'triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'input_data' => 'array',
            'output_data' => 'array',
            'success' => 'boolean',
            'triggered_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
