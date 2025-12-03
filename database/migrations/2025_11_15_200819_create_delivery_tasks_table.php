<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('delivery_tasks', function (Blueprint $table) {
            $table->id();
            
            //
            $table->enum('task_type', ['medicine_only', 'care_provider_only', 'both']);

            // حالة المهمة
            $table->enum('status', [
                'pending',        // طلب جاهز للسائق
                'assigned',       // تم تعيين سائق
                'picked_up',      // السائق أخذ الطلب / أخذ الممرض
                'delivering',     // بالطريق
                'delivered',      // وصل
                'cancelled'
            ])->default('pending');

            // السائق
            $table->foreignId('delivery_id')
                  ->nullable()
                  ->constrained('deliveries')
                  ->nullOnDelete();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            // بيانات المريض Snapshot
            $table->unsignedBigInteger('patient_id')->nullable(); // بدون FK
            $table->string('patient_name');
            $table->string('patient_phone', 32);
            $table->text('patient_address');
            $table->decimal('patient_lat', 10, 8)->nullable();
            $table->decimal('patient_lng', 11, 8)->nullable();

            // روابط اختيارية – بدون FK
            $table->unsignedBigInteger('order_id')->nullable();   // لو في دواء
            $table->unsignedBigInteger('visit_id')->nullable();   // لو في مقدم رعاية
            $table->unsignedBigInteger('care_provider_id')->nullable(); // لو “both”

            // Snapshot لمقدم الرعاية
            $table->string('care_provider_name')->nullable();
            $table->string('care_provider_phone', 32)->nullable();

            // ملاحظات للسائق
            $table->text('notes_for_driver')->nullable();

            $table->timestamps();

            // Indexes لتحسين السرعة
            $table->index('status');
            $table->index('delivery_id');
            $table->index('patient_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_tasks');
    }
};
