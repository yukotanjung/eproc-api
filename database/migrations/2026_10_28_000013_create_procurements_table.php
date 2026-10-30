<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurements', function (Blueprint $table) {
            $table->id();
            $table->string('job_number', 100);
            $table->string('name', 255);
            $table->decimal('budget_ceiling', 18, 2);
            $table->decimal('estimated_price', 18, 2);
            $table->date('planned_date');
            $table->foreignId('job_type_id')
                ->constrained('job_types', 'id', 'proc_job_type_fk')
                ->cascadeOnDelete();
            $table->foreignId('qualification_id')
                ->constrained('qualifications', 'id', 'proc_qualification_fk')
                ->cascadeOnDelete();
            $table->foreignId('funding_source_id')
                ->constrained('funding_sources', 'id', 'proc_funding_src_fk')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('budget_year');
            $table->string('project_location', 255);
            $table->foreignId('procurement_method_id')
                ->constrained('procurement_methods', 'id', 'proc_method_fk')
                ->cascadeOnDelete();
            $table->string('announcement_location', 255);
            $table->boolean('is_active')->default(true);
            $table->string('status', 50)->default('draft');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurements');
    }
};
