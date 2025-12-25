<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Historical note:
        // Migration 002 originally defaulted use_jwks_discovery=true, which unintentionally enabled
        // discovery for all existing provider rows on upgrade. To preserve backwards compatibility,
        // we explicitly disable discovery for rows that have a manual jwt_public_key configured.
        //
        // Admins can re-enable discovery per-provider in the UI.
        DB::table('generic_oidc_providers')
            ->where('use_jwks_discovery', true)
            ->whereNotNull('jwt_public_key')
            ->update(['use_jwks_discovery' => false]);
    }

    public function down(): void
    {
        // No-op: we can't safely infer prior intent.
    }
};

