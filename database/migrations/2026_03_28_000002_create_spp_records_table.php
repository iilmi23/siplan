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
        Schema::create('spp_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_uuid')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('port_id')->nullable()->constrained('ports')->nullOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_file')->nullable();
            $table->string('sheet_name')->nullable();
            $table->string('status', 20)->default('generated');
            $table->unsignedInteger('source_batch_count')->default(0);
            $table->unsignedInteger('record_count')->default(0);
            $table->integer('total_qty')->default(0);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index(['status', 'customer_id', 'created_at'], 'spp_batches_status_customer_idx');
        });

        Schema::create('spp_batch_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spp_batch_id')->constrained('spp_batches')->cascadeOnDelete();
            $table->foreignId('upload_batch_id')->constrained('upload_batches')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['spp_batch_id', 'upload_batch_id'], 'spp_batch_sources_unique');
            $table->index(['upload_batch_id']);
        });

        Schema::create('spp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spp_batch_id')->nullable()->constrained('spp_batches')->cascadeOnDelete();
            $table->foreignId('upload_batch_id')->nullable()->constrained('upload_batches')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('port_id')->nullable()->constrained('ports')->nullOnDelete();
            $table->foreignId('assy_id')->nullable()->constrained('assy')->nullOnDelete();

            $table->string('type')->nullable();
            $table->string('assy_number', 50);
            $table->string('level', 20)->nullable();
            $table->string('assy_code', 20)->nullable();
            $table->string('cct', 20)->nullable();
            $table->integer('std_pack')->nullable();
            $table->decimal('umh', 10, 6)->nullable();

            $table->string('period', 7);
            $table->string('order_type', 20)->nullable();

            $table->integer('bal_qty')->default(0);
            $table->integer('del_qty')->default(0);
            $table->integer('prod_qty')->default(0);
            $table->integer('total_qty')->default(0);

            $table->json('extra')->nullable();
            $table->timestamps();

            $table->unique(['upload_batch_id', 'assy_number', 'period'], 'spp_batch_assy_period_unique');
            $table->unique(['spp_batch_id', 'assy_number', 'period'], 'spp_plan_assy_period_unique');
            $table->index(['spp_batch_id', 'period'], 'spp_batch_period_index');
            $table->index(['customer_id', 'period'], 'spp_customer_id_period_index');
            $table->index(['port_id', 'period'], 'spp_port_period_index');
            $table->index(['assy_number', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spp');
        Schema::dropIfExists('spp_batch_sources');
        Schema::dropIfExists('spp_batches');
    }
};
