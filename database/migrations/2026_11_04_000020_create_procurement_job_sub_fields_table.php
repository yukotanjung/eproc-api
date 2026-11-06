<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_job_sub_fields', function (Blueprint $table) {
            $table->foreignId('procurement_id')
                ->constrained('procurements', 'id', 'pjsf_procurement_fk')
                ->cascadeOnDelete();
            $table->foreignId('job_sub_field_id')
                ->constrained('job_sub_fields', 'id', 'pjsf_sub_field_fk')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['procurement_id', 'job_sub_field_id'], 'pjsf_proc_sub_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_job_sub_fields');
    }
};
