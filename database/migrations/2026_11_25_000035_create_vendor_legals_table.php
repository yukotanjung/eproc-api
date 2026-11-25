<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_legals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->date('establishment_date');
            $table->string('establishment_deed_number');
            $table->string('establishment_notary');
            $table->string('establishment_deed_path')->nullable();
            $table->date('amendment_date')->nullable();
            $table->string('amendment_deed_number')->nullable();
            $table->string('amendment_notary')->nullable();
            $table->string('amendment_deed_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_legals');
    }
};
