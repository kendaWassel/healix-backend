<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationSymptom extends Model
{
    protected $fillable = [
        'conversation_id',
        'symptom_text',
        'negated',
        'confidence',
        'extracted_at',
    ];

    protected $casts = [
        'negated' => 'boolean',
        'confidence' => 'float',
        'extracted_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
