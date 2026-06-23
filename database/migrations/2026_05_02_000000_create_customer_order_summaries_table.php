<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_batch_id')->constrained('upload_batches')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('port_id')->nullable()->constrained('ports')->nullOnDelete();
            $table->foreignId('assy_id')->nullable()->constrained('assy')->nullOnDelete();
            $table->foreignId('production_week_id')->nullable()->constrained('production_weeks')->nullOnDelete();
            $table->date('etd')->nullable();
            $table->date('eta')->nullable();
            $table->string('month')->nullable();
            $table->string('week')->nullable();
            $table->string('assy_number');
            $table->string('order_type')->nullable();
            $table->unsignedInteger('line_count')->default(0);
            $table->integer('total_qty')->default(0);
            $table->timestamps();

            $table->index(['customer_id', 'production_week_id']);
            $table->index(['customer_id', 'assy_number'], 'summaries_customer_assy_siplan_idx');
            $table->index(['upload_batch_id', 'assy_number'], 'summaries_batch_assy_index');
            $table->index(['upload_batch_id', 'order_type'], 'summaries_variance_batch_order_index');
            $table->index(['assy_number'], 'summaries_variance_assy_index');
            $table->index(['production_week_id'], 'summaries_production_week_index');
            $table->index(['upload_batch_id', 'etd'], 'summaries_batch_etd_index');
            $table->index(['upload_batch_id', 'order_type', 'etd', 'week'], 'summaries_period_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summaries');
    }
};
