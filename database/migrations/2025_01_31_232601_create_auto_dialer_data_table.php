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
        Schema::create('auto_dialer_data', function (Blueprint $table) {
            $table->id();
            $table->string('auto_dailer_file_id');
            $table->string('mobile');
            $table->string('state');
            $table->string('provider_name');
            $table->string('extension');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_dialer_data');
    }
};
