<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records what a sale line's stock cost at the moment it was sold.
 *
 * Stock is derived from the ledger and never stored, but the cost of a sale is
 * different: it is a fact about one moment, not a running total. Deriving it on
 * read would mean an old sale's profit quietly changes the next time the item is
 * bought at a new price, because the average it was valued at moves underneath
 * it. So the figure is frozen when the line is written, the way base_qty and
 * total already are.
 *
 * Nullable, and deliberately not backfilled. A line with no cost is one recorded
 * before this existed, and it is shown as unknown rather than as free stock,
 * which would claim a 100% margin that never happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table): void {
            $table->decimal('cost', 14, 2)->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table): void {
            $table->dropColumn('cost');
        });
    }
};
