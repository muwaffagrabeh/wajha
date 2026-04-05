<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use HasUlids;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'raw_output',
        'intent',
        'action_taken',
        'confidence',
        'validation_result',
        'was_blocked',
        'block_reason',
        'tokens_used',
        'response_ms',
    ];

    protected function casts(): array
    {
        return [
            'raw_output' => 'array',
            'validation_result' => 'array',
            'was_blocked' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
