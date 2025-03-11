<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('general__settings', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('description');
        });

        // Insert a default logo path if no logo exists
        DB::table('general__settings')->insertOrIgnore([
            'key' => 'logo',
            'value' => 'logos/default.png', // Default logo path
            'description' => 'Application Logo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('general__settings', function (Blueprint $table) {
            $table->dropColumn('logo');
        });

        // Remove logo entry
        DB::table('general__settings')->where('key', 'logo')->delete();
    }
};
