<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Data ownership is intentionally migrated by stockly:migrate-tenancy,
        // where the operator can choose the owner and review a dry run first.
    }

    public function down(): void
    {
        // Ownership backfills are intentionally not destructive.
    }
};
