<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('role_user', function (Blueprint $table) {
            $table->uuid('role_id');
            $table->uuid('user_id');
            $table->primary(['role_id', 'user_id']);
        });
        Schema::create('role_permission', function (Blueprint $table) {
            $table->uuid('role_id');
            $table->uuid('permission_id');
            $table->primary(['role_id', 'permission_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('role_permission');
    }
};
