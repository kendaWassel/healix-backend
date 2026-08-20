<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_tasks', function (Blueprint $table) {
            // Set once the patient has been notified that the driver is
            // nearby, so the check (run on every GPS ping) fires the
            // notification only the first time the threshold is crossed
            // for a given task, not on every subsequent ping.
            $table->timestamp('patient_notified_nearby_at')->nullable()->after('delivered_at');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_tasks', function (Blueprint $table) {
            $table->dropColumn('patient_notified_nearby_at');
        });
    }
};
