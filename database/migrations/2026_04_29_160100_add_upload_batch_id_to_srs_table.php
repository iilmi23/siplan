<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('srs', function (Blueprint $table) {
            $table->foreignId('upload_batch_id')
                ->nullable()
                ->after('assy_id')
                ->constrained('upload_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('srs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('upload_batch_id');
        });
    }
};