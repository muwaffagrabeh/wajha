<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchServiceOverride extends Model
{
    use HasUlids;

    protected $fillable = [
        'branch_id',
        'service_item_id',
        'price_override',
        'available',
        'stock_override',
    ];

    protected function casts(): array
    {
        return [
            'price_override' => 'decimal:2',
            'available' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function serviceItem(): BelongsTo
    {
        return $this->belongsTo(ServiceItem::class);
    }
}
