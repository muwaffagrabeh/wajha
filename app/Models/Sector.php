<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'label',
        'label_en',
        'icon',
        'sort_order',
        'status',
    ];

    public function types(): HasMany
    {
        return $this->hasMany(SectorType::class);
    }
}
