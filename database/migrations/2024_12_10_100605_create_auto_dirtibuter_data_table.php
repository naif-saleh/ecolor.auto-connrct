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
        Schema::create('auto_dirtibuter_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auto_dirtibuter_id')->constrained('auto_dirtibuters')->onDelete('cascade');
            $table->string('mobile');
            $table->string('provider_name');
            $table->string('extension');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_dirtibuter_data');
    }
};
