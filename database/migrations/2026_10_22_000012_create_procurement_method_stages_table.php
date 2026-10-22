<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_method_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_method_id')
                ->constrained('procurement_methods', 'id', 'pms_method_fk')
                ->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('name', 150);
            $table->unsignedSmallInteger('duration_days');
            // calendar | working_day; a plain string because yajra's enum() emits no CHECK constraint.
            $table->string('day_type', 20);
            $table->boolean('requires_file')->default(false);
            $table->string('page_key', 100);
            $table->string('process_key', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['procurement_method_id', 'sequence'], 'pms_method_seq_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_method_stages');
    }
};
