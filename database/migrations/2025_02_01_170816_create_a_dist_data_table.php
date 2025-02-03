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
        Schema::create('a_dist_data', function (Blueprint $table) {
            $table->id();
            $table->string('mobile');
            $table->string('state')->default('new');
            $table->string('call_date')->nullable();
            $table->string('call_id')->nullable();
            $table->unsignedBigInteger('feed_id');
            $table->foreign('feed_id')->references('id')->on('a_dist_feeds')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('a_dist_data');
    }
};
