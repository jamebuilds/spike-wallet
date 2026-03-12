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
        Schema::table('sd_jwt_credentials', function (Blueprint $table) {
            $table->string('format')->default('vc+sd-jwt')->after('vct');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sd_jwt_credentials', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }
};
