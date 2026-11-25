<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_directors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('name');
            $table->string('ktp_number');
            $table->string('npwp_number');
            $table->string('position');
            $table->boolean('is_pic')->default(false);
            $table->string('photo_path')->nullable();
            $table->string('npwp_path')->nullable();
            $table->string('ktp_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_directors');
    }
};
