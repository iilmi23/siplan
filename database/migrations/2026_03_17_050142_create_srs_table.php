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
        Schema::create('srs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('port_id')->nullable()->constrained('ports')->nullOnDelete();
            $table->foreignId('carline_id')->nullable()->constrained('carline')->onDelete('restrict');
            $table->string('sr_number')->nullable();

            $table->string('assy_number')->nullable();
            $table->integer('qty')->nullable();
            $table->integer('total')->nullable();
            $table->date('delivery_date')->nullable();

            $table->date('etd')->nullable();
            $table->date('eta')->nullable();

            $table->string('route')->nullable();
            $table->string('order_type', 20)->nullable();

            $table->foreignId('assy_id')->nullable()->constrained('assy')->onDelete('restrict');
            $table->foreignId('upload_batch_id')->nullable()->constrained('upload_batches')->nullOnDelete();
            $table->foreignId('production_week_id')->nullable()->constrained('production_weeks')->nullOnDelete();
            $table->boolean('is_mapped')->default(false);
            $table->text('mapping_error')->nullable();

            $table->json('extra')->nullable();

            $table->timestamps();

            $table->index(['upload_batch_id', 'order_type'], 'srs_variance_batch_order_index');
            $table->index(['assy_number'], 'srs_variance_assy_index');
            $table->index(['etd', 'eta'], 'srs_variance_dates_index');
            $table->index(['production_week_id'], 'srs_production_week_index');
            $table->index(['upload_batch_id', 'created_at'], 'srs_batch_created_siplan_idx');
            $table->index(['customer_id', 'created_at'], 'srs_customer_created_siplan_idx');
            $table->index(['assy_number'], 'srs_assy_number_siplan_idx');
            $table->index(['etd', 'eta'], 'srs_etd_eta_siplan_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('srs');
    }
};
