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
        'audio_path',
        'transcribed_text',
        'detected_symptoms',
        'status',
    ];

    protected $casts = [
        'detected_symptoms' => 'array',
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
