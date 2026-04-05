<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessPolicy extends Model
{
    use HasUlids;

    protected $fillable = [
        'business_id',
        'key',
        'value',
        'display_text',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
