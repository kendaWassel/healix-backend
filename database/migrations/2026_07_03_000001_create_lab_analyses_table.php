<?php

use App\Models\Patient;
use App\Models\Upload;
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
        Schema::create('lab_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Patient::class)->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignIdFor(Upload::class)->nullable()->constrained()->nullOnDelete();
            $table->string('report_id')->unique();
            $table->string('overall_severity')->default('normal');
            $table->text('summary')->nullable();
            $table->unsignedInteger('total_tests_analyzed')->default(0);
            $table->unsignedInteger('abnormal_count')->default(0);
            $table->unsignedInteger('normal_count')->default(0);
            $table->json('patient_info')->nullable();
            $table->json('test_results')->nullable();
            $table->json('conditions')->nullable();
            $table->text('disclaimer')->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('patient_pdf_path')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lab_analyses');
    }
};
