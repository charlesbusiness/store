<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Points the optional customer name at a sale instead of the shared transaction.
 *
 * The column and its unique index are renamed rather than recreated so the
 * existing rows survive; the foreign key moves from transactions to sales.
 *
 * Each step is a separate statement, and the foreign key is always dropped
 * before its backing unique index. On MySQL that index cannot be dropped while
 * the constraint still depends on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_names', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
        });

        Schema::table('customer_names', function (Blueprint $table) {
            $table->dropUnique(['transaction_id']);
        });

        Schema::table('customer_names', function (Blueprint $table) {
            $table->renameColumn('transaction_id', 'sale_id');
        });

        Schema::table('customer_names', function (Blueprint $table) {
            $table->unique('sale_id');
        });

        Schema::table('customer_names', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_names', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
        });

        Schema::table('customer_names', function (Blueprint $table) {
            $table->dropUnique(['sale_id']);
        });

        Schema::table('customer_names', function (Blueprint $table) {
            $table->renameColumn('sale_id', 'transaction_id');
        });

        Schema::table('customer_names', function (Blueprint $table) {
            $table->unique('transaction_id');
        });

        Schema::table('customer_names', function (Blueprint $table) {
            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
        });
    }
};
