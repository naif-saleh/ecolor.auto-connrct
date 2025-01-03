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
        Schema::create('auto_distributerer_extensions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('lastName')->default('');
            $table->string('extension');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('3cx_user_id')->default('');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_distributerer_extensions');
    }
};
