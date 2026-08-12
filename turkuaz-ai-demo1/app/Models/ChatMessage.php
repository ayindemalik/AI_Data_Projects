<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_session_id', 'role', 'content', 'matched_product_ids', 'source',
        'rating', 'feedback_note', 'rated_at',
    ];

    protected $casts = [
        'matched_product_ids' => 'array',
        'rated_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    /**
     * Answers the user marked as unhelpful — the queue to review when tuning
     * the assistant.
     */
    public function scopeRatedDown(Builder $query): Builder
    {
        return $query->where('rating', -1);
    }

    /**
     * True when the answer came from the model with product data behind it.
     * A down-vote here is a prompt/answer problem worth acting on; a down-vote
     * on a 'db' (OpenAI failed) or 'rule' (price shortcut) reply is not.
     */
    public function isGeneratedAnswer(): bool
    {
        return $this->source === 'openai' && !empty($this->matched_product_ids);
    }
}
