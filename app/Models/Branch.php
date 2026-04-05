<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasUlids;

    protected $fillable = [
        'business_id',
        'name',
        'city',
        'district',
        'address',
        'lat',
        'lng',
        'phone',
        'whatsapp',
        'working_hours',
        'is_default',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'working_hours' => 'array',
            'is_default' => 'boolean',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function specialists(): HasMany
    {
        return $this->hasMany(Specialist::class);
    }

    public function serviceOverrides(): HasMany
    {
        return $this->hasMany(BranchServiceOverride::class);
    }
}
