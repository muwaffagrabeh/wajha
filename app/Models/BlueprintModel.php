<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlueprintModel extends Model
{
    protected $table = 'blueprints';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    protected $fillable = ['id', 'label', 'label_en', 'base_flow', 'base_features', 'requires', 'optional'];

    protected function casts(): array
    {
        return [
            'base_flow' => 'array',
            'base_features' => 'array',
            'requires' => 'array',
            'optional' => 'array',
        ];
    }
}
