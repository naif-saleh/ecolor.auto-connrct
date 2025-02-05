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
        Schema::create('a_dial_skipped_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('mobile');
            $table->string('message');
            $table->string('provider_id');
            $table->string('feed_id');
            $table->unsignedBigInteger('uploaded_by');

            $table->foreign('uploaded_by')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('a_dial_skipped_numbers');
    }
};
