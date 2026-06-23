<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ports', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('set null');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['customer_id', 'name'], 'ports_customer_name_unique');
            $table->index('name');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ports');
    }
};