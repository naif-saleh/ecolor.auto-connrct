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
        Schema::table('auto_dailer_provider_feeds', function (Blueprint $table) {
            $table->string('call_id')->nullable()->after('state'); // Add 'call_id' column
            $table->string('party_dn_type')->nullable()->after('call_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_dailer_provider_feeds', function (Blueprint $table) {
            $table->dropColumn(['call_id', 'party_dn_type']);
        });
    }
};
