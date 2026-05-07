<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_weeks', function (Blueprint $table) {
            if (!Schema::hasColumn('production_weeks', 'end_date')) {
                $table->date('end_date')->nullable()->after('week_start');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_weeks', function (Blueprint $table) {
            if (Schema::hasColumn('production_weeks', 'end_date')) {
                $table->dropColumn('end_date');
            }
        });
    }
};
