<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('care_providers', function (Blueprint $table) {
            if (!Schema::hasColumn('care_providers', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('rating_avg');
            }

            if (!Schema::hasColumn('care_providers', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }

            if (!Schema::hasColumn('care_providers', 'available')) {
                $table->boolean('available')->default(true)->after('longitude');
            }

            if (!Schema::hasColumn('care_providers', 'last_location_updated_at')) {
                $table->timestamp('last_location_updated_at')->nullable()->after('available');
            }
        });
    }

    public function down(): void
    {
        Schema::table('care_providers', function (Blueprint $table) {
            if (Schema::hasColumn('care_providers', 'last_location_updated_at')) {
                $table->dropColumn('last_location_updated_at');
            }

            if (Schema::hasColumn('care_providers', 'available')) {
                $table->dropColumn('available');
            }

            if (Schema::hasColumn('care_providers', 'longitude')) {
                $table->dropColumn('longitude');
            }

            if (Schema::hasColumn('care_providers', 'latitude')) {
                $table->dropColumn('latitude');
            }
        });
    }
};