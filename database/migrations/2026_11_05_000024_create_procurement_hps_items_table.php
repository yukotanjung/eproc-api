<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_hps_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')
                ->constrained('procurements', 'id', 'phi_procurement_fk')
                ->cascadeOnDelete();
            $table->foreignId('unit_id')
                ->constrained('units', 'id', 'phi_unit_fk')
                ->cascadeOnDelete();
            $table->string('name', 255);
            $table->unsignedBigInteger('quantity');
            $table->decimal('unit_price', 18, 2);
            $table->boolean('use_tax')->default(false);
            $table->decimal('tax_rate', 5, 2)->default(11.00);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['procurement_id', 'unit_id'], 'phi_proc_unit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_hps_items');
    }
};
