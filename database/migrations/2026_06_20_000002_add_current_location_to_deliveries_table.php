<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->decimal('current_latitude', 10, 7)->nullable()->after('plate_number');
            $table->decimal('current_longitude', 10, 7)->nullable()->after('current_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['current_latitude', 'current_longitude']);
        });
    }
};
