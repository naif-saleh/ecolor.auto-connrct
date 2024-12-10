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
        Schema::create('auto_dailer_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('auto_dailer_id');
            $table->string('mobile');
            $table->string('provider_name');
            $table->string('extension');
            $table->timestamps();

            $table->foreign('auto_dailer_id')->references('id')->on('auto_dailers')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_dailer_data');
    }
};
