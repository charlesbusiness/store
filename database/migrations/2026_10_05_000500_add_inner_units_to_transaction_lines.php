<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a purchase declare what one trading unit is made of.
 *
 * A carton of thyme that holds 12 rolls and 24 sachets is recorded on the line
 * that received it, so a later sale can be typed in any of those units. This is
 * a snapshot of the declaration, not a change to how stock is counted: base_qty
 * keeps its meaning, so existing rows need no backfill and every figure that
 * aggregates base_qty keeps working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table): void {
            // [{"unit": "roll", "per": 12}, {"unit": "sachet", "per": 24}]
            $table->json('inner_units')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table): void {
            $table->dropColumn('inner_units');
        });
    }
};
