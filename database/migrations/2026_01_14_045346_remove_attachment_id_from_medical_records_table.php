<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('medical_records')) {
            return;
        }

        if (Schema::hasColumn('medical_records', 'attachment_id')) {
            // حاول حذف الـ foreign key إن وجد، وإذا ما كان موجود لا توقف المايغريشن
            try {
                Schema::table('medical_records', function (Blueprint $table) {
                    $table->dropForeign(['attachment_id']);
                });
            } catch (\Throwable $e) {
                // تجاهل الخطأ إذا الـ FK غير موجود
            }

            Schema::table('medical_records', function (Blueprint $table) {
                $table->dropColumn('attachment_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('medical_records')) {
            return;
        }

        if (!Schema::hasColumn('medical_records', 'attachment_id')) {
            Schema::table('medical_records', function (Blueprint $table) {
                $table->foreignId('attachment_id')
                    ->nullable()
                    ->constrained('uploads')
                    ->cascadeOnDelete();
            });
        }
    }
};