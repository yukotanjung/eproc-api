<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->string('job_name');
            $table->string('contract_number');
            $table->decimal('contract_value', 18, 2);
            $table->foreignId('job_type_id')->constrained('job_types');
            $table->string('client_name');
            $table->string('location');
            $table->text('address');
            $table->date('contract_date');
            $table->date('contract_end_date');
            $table->string('contract_file_path')->nullable();
            $table->date('handover_date');
            $table->string('handover_number');
            $table->string('handover_file_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_experiences');
    }
};
