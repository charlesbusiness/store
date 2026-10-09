<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional declared cost per unit for the line's own unit.
 *
 * On a purchase, the trader may record what that unit cost at the time (e.g.
 * cost per bag). It is optional and nullable, and never backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table): void {
            $table->decimal('unit_cost', 14, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table): void {
            $table->dropColumn('unit_cost');
        });
    }
};
