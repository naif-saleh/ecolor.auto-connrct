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
        Schema::create('a_dist_agents', function (Blueprint $table) {
            $table->id();
            $table->string('three_cx_user_id')->nullable();
            $table->string('user_id')->nullable();
            $table->string('firstName')->nullable();
            $table->string('lastName')->nullable();
            $table->string('displayName')->nullable();
            $table->string('email')->nullable();
            $table->boolean('isRegistred')->nullable();
            $table->string('QueueStatus')->nullable();
            $table->string('extension')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('a_dist_agents');
    }
};
