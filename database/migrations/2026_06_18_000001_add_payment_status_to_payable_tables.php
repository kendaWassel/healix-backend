<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            if (! Schema::hasColumn('consultations', 'payment_status')) {
                $table->string('payment_status')->default('pending')->after('status');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'payment_status')) {
                $table->string('payment_status')->default('pending')->after('status');
            }
        });

        Schema::table('home_visits', function (Blueprint $table) {
            if (! Schema::hasColumn('home_visits', 'payment_status')) {
                $table->string('payment_status')->default('pending')->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            if (Schema::hasColumn('consultations', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
        });

        Schema::table('home_visits', function (Blueprint $table) {
            if (Schema::hasColumn('home_visits', 'payment_status')) {
                $table->dropColumn('payment_status');
            }
        });
    }
};
