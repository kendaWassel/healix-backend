<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('delivery_tasks')->onDelete('cascade');
            $table->foreignId('delivery_id')->constrained('deliveries')->onDelete('cascade');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_locations');
    }
};
