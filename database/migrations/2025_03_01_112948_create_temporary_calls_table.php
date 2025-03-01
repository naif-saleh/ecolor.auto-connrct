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
        Schema::create('temporary_calls', function (Blueprint $table) {
            $table->id();
            $table->string('call_id')->unique();
            $table->string('provider')->nullable();
            $table->string('extension')->nullable();
            $table->string('phone_number')->nullable();
            $table->text('call_data'); // Store full JSON call data
            $table->string('status')->default('pending'); // Track processing status
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_calls');
    }
};
