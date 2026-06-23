<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assy', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carline_id')
                ->constrained('carline')
                ->onDelete('restrict');
            $table->string('assy_code', 20)->unique();
            $table->string('assy_number', 50)->unique();
            $table->string('level', 20);
            $table->string('pattern')->nullable();
            $table->integer('standard_sea_quantity')->nullable();
            $table->integer('standard_air_quantity')->nullable();
            $table->integer('max_quantity_sea')->nullable();
            $table->integer('max_quantity_air')->nullable();
            $table->decimal('umh', 10, 6);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['carline_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assy');
    }
};
