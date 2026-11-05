<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_checklist_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')
                ->constrained('procurements', 'id', 'pcf_procurement_fk')
                ->cascadeOnDelete();
            $table->foreignId('procurement_schedule_id')
                ->constrained('procurement_schedules', 'id', 'pcf_schedule_fk')
                ->cascadeOnDelete();
            $table->string('name', 255);
            $table->unsignedInteger('max_size');
            $table->text('file_types');
            $table->boolean('is_required')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['procurement_id', 'procurement_schedule_id'], 'pcf_proc_sched_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_checklist_files');
    }
};
