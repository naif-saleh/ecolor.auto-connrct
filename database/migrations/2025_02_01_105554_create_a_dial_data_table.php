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
        Schema::create('a_dial_data', function (Blueprint $table) {
            $table->id();
            $table->string('feed_id');
            $table->string('mobile');
            $table->string('state');
            $table->string('call_id')->default('0');
            $table->string('call_date')->default('0');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('a_dial_data');
    }
};
