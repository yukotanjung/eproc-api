<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')
                ->constrained('procurements', 'id', 'pd_procurement_fk')
                ->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('file_path', 500);
            $table->string('file_name', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('extension', 20)->nullable();
            $table->boolean('is_public')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['procurement_id', 'is_public'], 'pd_proc_pub_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_documents');
    }
};
