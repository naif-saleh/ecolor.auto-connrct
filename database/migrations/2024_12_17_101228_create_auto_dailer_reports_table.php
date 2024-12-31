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
        Schema::create('auto_dailer_reports', function (Blueprint $table) {
            $table->id();
            $table->string('call_id')->require();
            $table->string('status')->require();
            $table->string('phone_number')->require();
            $table->string('provider')->require();
            $table->string('extension')->require();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_dailer_reports');
    }
};
