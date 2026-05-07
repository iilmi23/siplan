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
        Schema::table('assy', function (Blueprint $table) {
            $table->dropForeign(['carline_id']);
            $table->foreignId('carline_id')->nullable()->change();
            $table->foreign('carline_id')->references('id')->on('carline')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assy', function (Blueprint $table) {
            $table->dropForeign(['carline_id']);
            $table->foreignId('carline_id')->nullable(false)->change();
            $table->foreign('carline_id')->references('id')->on('carline')->onDelete('restrict');
        });
    }
};
