<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->foreignId('consultation_id')->nullable()->after('target_id')->constrained('consultations')->nullOnDelete();
            $table->foreignId('order_id')->nullable()->after('consultation_id')->constrained('orders')->nullOnDelete();
            $table->foreignId('home_visit_id')->nullable()->after('order_id')->constrained('home_visits')->nullOnDelete();
            $table->foreignId('delivery_task_id')->nullable()->after('home_visit_id')->constrained('delivery_tasks')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropForeign(['consultation_id']);
            $table->dropForeign(['order_id']);
            $table->dropForeign(['home_visit_id']);
            $table->dropForeign(['delivery_task_id']);
            $table->dropColumn(['consultation_id', 'order_id', 'home_visit_id', 'delivery_task_id']);
        });
    }
};
