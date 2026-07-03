<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('sender_id')
                ->nullable()
                ->after('conversation_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('message_type', ['text', 'voice'])
                ->default('text')
                ->after('sender_id');

            $table->string('audio_path')->nullable()->after('message');
            $table->text('transcribed_text')->nullable()->after('audio_path');

            $table->enum('status', ['uploaded', 'transcribed', 'failed'])
                ->nullable()
                ->after('transcribed_text');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->text('message')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['sender_id']);
            $table->dropColumn([
                'sender_id',
                'message_type',
                'audio_path',
                'transcribed_text',
                'status',
            ]);

            $table->text('message')->nullable(false)->change();
        });
    }
};
