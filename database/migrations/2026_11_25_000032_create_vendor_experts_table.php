<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_experts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('name');
            $table->string('birth_place');
            $table->date('birth_date');
            $table->string('position');
            $table->string('npwp_number');
            $table->string('ktp_number');
            $table->text('address');
            $table->foreignId('education_level_id')->constrained('education_levels');
            $table->string('major');
            $table->string('diploma_number');
            $table->unsignedInteger('experience_years');
            $table->string('photo_path')->nullable();
            $table->string('diploma_path')->nullable();
            $table->string('certificate_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_experts');
    }
};
