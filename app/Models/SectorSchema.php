<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectorSchema extends Model
{
    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'sector_type_id',
        'attribute_key',
        'label',
        'label_en',
        'type',
        'options',
        'required',
        'show_to_customer',
        'filterable',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'required' => 'boolean',
            'show_to_customer' => 'boolean',
            'filterable' => 'boolean',
        ];
    }

    public function sectorType(): BelongsTo
    {
        return $this->belongsTo(SectorType::class);
    }
}
