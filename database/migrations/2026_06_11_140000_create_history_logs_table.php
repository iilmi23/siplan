<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('history_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('activity_type');
            $table->string('title');
            $table->string('status')->nullable();
            $table->string('customer_code')->nullable();
            $table->string('port_name')->nullable();
            $table->string('source_file')->nullable();
            $table->string('batch_uuid')->nullable();
            $table->string('sheet_name')->nullable();
            $table->integer('record_count')->default(0);
            $table->bigInteger('total_qty')->default(0);
            $table->integer('mapped_count')->default(0);
            $table->integer('unmapped_count')->default(0);
            $table->integer('source_batch_count')->default(0);
            $table->unsignedBigInteger('related_id')->nullable();
            $table->text('notes')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('activity_type');
            $table->index('customer_code');
        });

        // Migrate existing upload_batches to history_logs
        $uploadBatches = DB::table('upload_batches')
            ->leftJoin('customers', 'upload_batches.customer_id', '=', 'customers.id')
            ->leftJoin('ports', 'upload_batches.port_id', '=', 'ports.id')
            ->select(
                'upload_batches.id as related_id',
                'upload_batches.uploaded_by as user_id',
                'upload_batches.batch_uuid',
                'upload_batches.source_file',
                'upload_batches.sheet_name',
                'upload_batches.status',
                'upload_batches.record_count',
                'upload_batches.mapped_count',
                'upload_batches.unmapped_count',
                'upload_batches.total_qty',
                'upload_batches.notes',
                'upload_batches.created_at',
                'upload_batches.updated_at',
                'customers.code as customer_code',
                'ports.name as port_name'
            )->get();

        foreach ($uploadBatches as $ub) {
            $repSr = DB::table('srs')
                ->where('upload_batch_id', $ub->related_id)
                ->orderBy('id')
                ->value('id');

            DB::table('history_logs')->insert([
                'user_id' => $ub->user_id,
                'activity_type' => 'sr_upload',
                'title' => 'SR Uploaded',
                'status' => $ub->status ?: 'completed',
                'customer_code' => $ub->customer_code,
                'port_name' => $ub->port_name,
                'source_file' => $ub->source_file,
                'batch_uuid' => $ub->batch_uuid,
                'sheet_name' => $ub->sheet_name,
                'record_count' => $ub->record_count ?: 0,
                'total_qty' => $ub->total_qty ?: 0,
                'mapped_count' => $ub->mapped_count ?: 0,
                'unmapped_count' => $ub->unmapped_count ?: 0,
                'source_batch_count' => 0,
                'related_id' => $repSr ?: $ub->related_id,
                'notes' => $ub->notes,
                'created_at' => $ub->created_at,
                'updated_at' => $ub->updated_at,
            ]);
        }

        // Migrate existing spp_batches to history_logs
        $sppBatches = DB::table('spp_batches')
            ->leftJoin('customers', 'spp_batches.customer_id', '=', 'customers.id')
            ->leftJoin('ports', 'spp_batches.port_id', '=', 'ports.id')
            ->select(
                'spp_batches.id as related_id',
                'spp_batches.generated_by as user_id',
                'spp_batches.batch_uuid',
                'spp_batches.source_file',
                'spp_batches.sheet_name',
                'spp_batches.status',
                'spp_batches.record_count',
                'spp_batches.total_qty',
                'spp_batches.source_batch_count',
                'spp_batches.notes',
                'spp_batches.created_at',
                'spp_batches.updated_at',
                'customers.code as customer_code',
                'ports.name as port_name'
            )->get();

        foreach ($sppBatches as $sb) {
            DB::table('history_logs')->insert([
                'user_id' => $sb->user_id,
                'activity_type' => 'spp_generated',
                'title' => 'SPP Generated',
                'status' => $sb->status ?: 'generated',
                'customer_code' => $sb->customer_code,
                'port_name' => $sb->port_name,
                'source_file' => $sb->source_file,
                'batch_uuid' => $sb->batch_uuid,
                'sheet_name' => $sb->sheet_name,
                'record_count' => $sb->record_count ?: 0,
                'total_qty' => $sb->total_qty ?: 0,
                'mapped_count' => 0,
                'unmapped_count' => 0,
                'source_batch_count' => $sb->source_batch_count ?: 0,
                'related_id' => $sb->related_id,
                'notes' => $sb->notes,
                'created_at' => $sb->created_at,
                'updated_at' => $sb->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('history_logs');
    }
};
