<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SetupRequest extends Model
{
    use HasUlids;

    protected $fillable = [
        'session_token', 'user_id', 'requested_type', 'matched_blueprint',
        'match_level', 'collected_data', 'status', 'reviewer_notes', 'reviewed_by',
    ];

    protected function casts(): array
    {
        return ['collected_data' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
