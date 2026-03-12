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
        Schema::table('users', function (Blueprint $table) {
            $table->json('wallet_public_jwk')->nullable()->after('two_factor_confirmed_at');
            $table->text('wallet_private_jwk')->nullable()->after('wallet_public_jwk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['wallet_public_jwk', 'wallet_private_jwk']);
        });
    }
};
