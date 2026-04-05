<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceItem extends Model
{
    use HasUlids;

    protected $fillable = [
        'business_id',
        'name',
        'name_en',
        'type',
        'category',
        'description',
        'price',
        'price_model',
        'price_unit',
        'currency',
        'duration_minutes',
        'requires_booking',
        'requires_specialist',
        'deliverable',
        'stock_quantity',
        'media',
        'attributes',
        'tags',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'requires_booking' => 'boolean',
            'requires_specialist' => 'boolean',
            'media' => 'array',
            'attributes' => 'array',
            'tags' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branchOverrides(): HasMany
    {
        return $this->hasMany(BranchServiceOverride::class);
    }
}
