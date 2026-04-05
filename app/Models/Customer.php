<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    use HasUlids;

    protected $fillable = [
        'business_id',
        'name',
        'phone',
        'email',
        'notes',
        'tags',
        'source',
        'first_contact_at',
        'last_contact_at',
        'total_bookings',
        'total_orders',
        'total_spent',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'first_contact_at' => 'datetime',
            'last_contact_at' => 'datetime',
            'total_spent' => 'decimal:2',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
