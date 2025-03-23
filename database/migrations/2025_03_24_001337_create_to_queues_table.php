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
        Schema::create('to_queues', function (Blueprint $table) {
            $table->id();
            $table->string('call_id')->require();
            $table->string('status')->require();
            $table->string('duration_time')->nullable();
            $table->string('duration_routing')->nullable();

            $table->unsignedBigInteger('a_dial_report_id');
            $table->foreign('a_dial_report_id')->references('id')->on('auto_dailer_reports')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('to_queues');
    }
};
