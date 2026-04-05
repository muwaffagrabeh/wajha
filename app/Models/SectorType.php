<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SectorType extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'sector_id',
        'label',
        'label_en',
        'default_patterns',
        'default_layers',
        'sector_rules',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'default_patterns' => 'array',
            'default_layers' => 'array',
        ];
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function schemas(): HasMany
    {
        return $this->hasMany(SectorSchema::class);
    }
}
