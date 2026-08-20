<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    public const TYPE_TEXT = 'text';
    public const TYPE_VOICE = 'voice';

    public const SENDER_PATIENT = 'patient';
    public const SENDER_ASSISTANT = 'assistant';

    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_TRANSCRIBED = 'transcribed';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender',
        'message_type',
        'message',
        'turn_number',
        'audio_path',
        'transcribed_text',
        'detected_symptoms',
        'status',
        'is_crisis',
        'severity',
        'diagnosis',
        'specialty',
        'reports',
    ];

    protected $casts = [
        'detected_symptoms' => 'array',
        'is_crisis' => 'boolean',
        'diagnosis' => 'array',
        'reports' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
