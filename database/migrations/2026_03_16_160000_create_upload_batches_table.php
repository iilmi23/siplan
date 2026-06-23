<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_uuid')->unique();
            $table->string('batch_type', 20)->default('sr');
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('port_id')->nullable()->constrained('ports')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_file');
            $table->unsignedInteger('sheet_index')->nullable();
            $table->string('sheet_name')->nullable();
            $table->string('status')->default('processing');
            $table->unsignedInteger('record_count')->default(0);
            $table->unsignedInteger('mapped_count')->default(0);
            $table->unsignedInteger('unmapped_count')->default(0);
            $table->unsignedInteger('total_qty')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index(['batch_type', 'customer_id', 'status', 'created_at'], 'upload_batches_type_customer_status_idx');
            $table->index(['status', 'customer_id', 'created_at'], 'upload_batches_variance_status_index');
            $table->index(['customer_id', 'status', 'created_at'], 'upload_batches_customer_status_siplan_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_batches');
    }
};
