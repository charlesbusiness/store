<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an optional declared selling price per unit for the line's own unit.
 *
 * On a purchase, the trader may record the intended selling price (e.g.
 * selling price per bag). It is optional and nullable, and never backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table): void {
            $table->decimal('selling_price', 14, 2)->nullable()->after('unit_cost');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table): void {
            $table->dropColumn('selling_price');
        });
    }
};
