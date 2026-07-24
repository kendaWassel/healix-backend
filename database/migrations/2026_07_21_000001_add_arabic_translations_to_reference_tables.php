<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes user-facing reference content bilingual.
 *
 * Design note — why paired columns instead of a JSON translations column:
 *
 *  1. `specializations.name` is a LOOKUP KEY, not just display text. Registration
 *     resolves a doctor's specialization by name, and the AI service returns
 *     `recommended_specialty` as an English string. Both need a plain, indexable
 *     column; a JSON blob would break the WHERE clause and any index on it.
 *  2. The project runs on SQLite locally and MySQL elsewhere. JSON path queries
 *     and ordering behave differently across the two; ordinary columns do not.
 *  3. There are exactly two locales. JSON's open-ended shape buys flexibility
 *     the product does not need, at the cost of casting on every read.
 *  4. No duplication: the existing column stays the canonical English value, so
 *     each translatable field gains exactly ONE new column.
 *
 * Only content actually rendered to end users is translated here. Clinical and
 * scientific data — medication names, dosages, drug identifiers, medical codes —
 * is deliberately left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('specializations', function (Blueprint $table) {
            if (! Schema::hasColumn('specializations', 'name_ar')) {
                $table->string('name_ar')->nullable()->after('name');
            }
        });

        Schema::table('faqs', function (Blueprint $table) {
            if (! Schema::hasColumn('faqs', 'question_ar')) {
                $table->text('question_ar')->nullable()->after('question');
            }
            if (! Schema::hasColumn('faqs', 'answer_ar')) {
                $table->text('answer_ar')->nullable()->after('answer');
            }
        });

        Schema::table('first_aids', function (Blueprint $table) {
            if (! Schema::hasColumn('first_aids', 'title_ar')) {
                $table->string('title_ar')->nullable()->after('title');
            }
            if (! Schema::hasColumn('first_aids', 'description_ar')) {
                $table->text('description_ar')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('specializations', function (Blueprint $table) {
            if (Schema::hasColumn('specializations', 'name_ar')) {
                $table->dropColumn('name_ar');
            }
        });

        Schema::table('faqs', function (Blueprint $table) {
            foreach (['question_ar', 'answer_ar'] as $column) {
                if (Schema::hasColumn('faqs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('first_aids', function (Blueprint $table) {
            foreach (['title_ar', 'description_ar'] as $column) {
                if (Schema::hasColumn('first_aids', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
