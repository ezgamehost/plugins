<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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

        // Backwards-compatibility backfill: if a provider has a manual jwt_public_key configured,
        // ensure JWKS discovery is disabled for it by default.
        DB::table('generic_oidc_providers')
            ->whereNotNull('jwt_public_key')
            ->update(['use_jwks_discovery' => false]);
    }

    public function down(): void
    {
        Schema::table('generic_oidc_providers', function (Blueprint $table) {
            $table->dropColumn('use_jwks_discovery');
        });
    }
};
