<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        Schema::create('production_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
                $table->unsignedBigInteger('customer_scope_id')->virtualAs('COALESCE(customer_id, 0)');
            }

            $table->integer('year');
            $table->integer('month_number');
            $table->string('month_name');
            $table->integer('week_no');
            $table->date('week_start');
            $table->date('end_date');
            $table->json('working_days')->nullable();
            $table->integer('total_working_days')->default(0);
            $table->integer('num_weeks');
            $table->timestamps();
            
            $table->unique(['customer_id', 'year', 'month_number', 'week_no'], 'weeks_unique');
            $table->index(['customer_id', 'week_start']);

            if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
                $table->unique(['customer_scope_id', 'year', 'month_number', 'week_no'], 'production_weeks_scope_unique');
            }
        });

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX production_weeks_scope_unique ON production_weeks ((COALESCE(customer_id, 0)), year, month_number, week_no)'
            );

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement(
                'CREATE UNIQUE INDEX production_weeks_scope_unique ON production_weeks (COALESCE(customer_id, 0), year, month_number, week_no)'
            );

            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE production_weeks ADD customer_scope_id AS ISNULL(customer_id, 0) PERSISTED');
            DB::statement(
                'CREATE UNIQUE INDEX production_weeks_scope_unique ON production_weeks (customer_scope_id, year, month_number, week_no)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_weeks');
    }
};
