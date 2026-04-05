<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Specialist extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'name',
        'role',
        'phone',
        'service_ids',
        'working_hours',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'service_ids' => 'array',
            'working_hours' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
