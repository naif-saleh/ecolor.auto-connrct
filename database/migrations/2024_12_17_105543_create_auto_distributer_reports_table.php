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
        Schema::create('auto_distributer_reports', function (Blueprint $table) {
            $table->id();
            $table->string('call_id')->require();
            $table->string('status')->require();
            $table->string('phone_number')->require();
            $table->string('provider')->nullable();
            $table->string('extension')->require();
            $table->boolean('is_satisfied')->default(false);
            $table->string('duration_time')->nullable();
            $table->string('duration_routing')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_distributer_reports');
    }
};
