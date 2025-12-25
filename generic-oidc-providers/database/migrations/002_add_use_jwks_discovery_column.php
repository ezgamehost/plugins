<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generic_oidc_providers', function (Blueprint $table) {
            // Default to false for backwards compatibility: existing providers that have a manually
            // configured jwt_public_key should not be forced into JWKS discovery behavior.
            $table->boolean('use_jwks_discovery')->default(false)->after('verify_jwt');
        });
    }

    public function down(): void
    {
        Schema::table('generic_oidc_providers', function (Blueprint $table) {
            $table->dropColumn('use_jwks_discovery');
        });
    }
};
