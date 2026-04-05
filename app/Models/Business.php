<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasUlids;

    protected $fillable = [
        'user_id',
        'sector_type_id',
        'name',
        'name_en',
        'logo',
        'description',
        'default_currency',
        'active_patterns',
        'active_layers',
        'custom_rules',
        'agent_name',
        'agent_tone',
        'agent_dialect',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'active_patterns' => 'array',
            'active_layers' => 'array',
            'custom_rules' => 'array',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sectorType(): BelongsTo
    {
        return $this->belongsTo(SectorType::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function serviceItems(): HasMany
    {
        return $this->hasMany(ServiceItem::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function policies(): HasMany
    {
        return $this->hasMany(BusinessPolicy::class);
    }
}
