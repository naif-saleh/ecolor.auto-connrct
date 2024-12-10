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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('allow_calling')->default(false);
            $table->boolean('allow_auto_calling')->default(false);
            $table->integer('cfd_start_time')->default(1);
            $table->integer('cfd_end_time')->default(24);
            $table->boolean('cfd_allow_friday')->default(false);
            $table->boolean('cfd_allow_saturday')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
