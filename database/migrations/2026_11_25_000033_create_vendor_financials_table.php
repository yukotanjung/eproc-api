<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_financials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->date('report_date');
            $table->decimal('fixed_assets', 18, 2)->default(0);
            $table->decimal('current_assets', 18, 2)->default(0);
            $table->decimal('other_assets', 18, 2)->default(0);
            $table->decimal('long_term_liabilities', 18, 2)->default(0);
            $table->decimal('short_term_liabilities', 18, 2)->default(0);
            $table->decimal('capital', 18, 2)->default(0);
            $table->decimal('net_worth', 18, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_financials');
    }
};
