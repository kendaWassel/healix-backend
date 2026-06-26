<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_task_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('delivery_tasks')->cascadeOnDelete();
            $table->foreignId('delivery_id')->constrained('deliveries')->cascadeOnDelete();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'expired'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'delivery_id']);
            $table->index(['delivery_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_task_candidates');
    }
};
