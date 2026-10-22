<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['remember_me', 'remember_token']);
            $table->string('username')->unique()->nullable();
            $table->string('employee_code')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'employee_code']);
            $table->string('remember_me')->nullable();
            $table->string('remember_token')->nullable();
        });
    }
};
