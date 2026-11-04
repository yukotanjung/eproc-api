<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')
                ->constrained('procurements', 'id', 'ps_procurement_fk')
                ->cascadeOnDelete();
            $table->foreignId('procurement_method_stage_id')
                ->nullable()
                ->constrained('procurement_method_stages', 'id', 'ps_method_stage_fk')
                ->nullOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('name', 150);
            $table->unsignedSmallInteger('duration_days');
            $table->string('day_type', 20);
            $table->boolean('requires_file')->default(false);
            $table->boolean('is_public')->default(true);
            $table->string('page_key', 100);
            $table->string('process_key', 100);
            $table->text('description')->nullable();
            $table->dateTime('start_at')->nullable();
            $table->dateTime('end_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['procurement_id', 'sequence'], 'ps_proc_seq_idx');
            $table->index(['start_at', 'end_at'], 'ps_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_schedules');
    }
};
