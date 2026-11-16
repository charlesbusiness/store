<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Copies the legacy ledger into the split tables, preserving ids.
 *
 * Ids are kept so stock_transfer_id and customer_names keep pointing at the same
 * rows without a remapping step. The copy runs in pure SQL (insert ... select) so
 * it works the same on MySQL and SQLite and never loads the ledger into memory.
 * The legacy tables are left untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales')->insertUsing(
            ['id', 'tenant_id', 'branch_id', 'transacted_on', 'user_id', 'paid', 'created_at', 'updated_at'],
            DB::table('transactions')
                ->where('type', 'sale')
                ->select('id', 'tenant_id', 'branch_id', 'transacted_on', 'user_id', 'paid', 'created_at', 'updated_at'),
        );

        DB::table('purchases')->insertUsing(
            ['id', 'tenant_id', 'branch_id', 'transacted_on', 'user_id', 'stock_transfer_id', 'created_at', 'updated_at'],
            DB::table('transactions')
                ->where('type', 'purchase')
                ->select('id', 'tenant_id', 'branch_id', 'transacted_on', 'user_id', 'stock_transfer_id', 'created_at', 'updated_at'),
        );

        DB::table('sale_lines')->insertUsing(
            ['id', 'sale_id', 'item_id', 'qty', 'unit_code', 'base_qty', 'unit_price', 'unit_cost', 'selling_price', 'total', 'cost', 'inner_units', 'created_at', 'updated_at'],
            DB::table('transaction_lines')
                ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
                ->where('transactions.type', 'sale')
                ->select([
                    'transaction_lines.id',
                    'transaction_lines.transaction_id as sale_id',
                    'transaction_lines.item_id',
                    'transaction_lines.qty',
                    'transaction_lines.unit_code',
                    'transaction_lines.base_qty',
                    'transaction_lines.unit_price',
                    'transaction_lines.unit_cost',
                    'transaction_lines.selling_price',
                    'transaction_lines.total',
                    'transaction_lines.cost',
                    'transaction_lines.inner_units',
                    'transaction_lines.created_at',
                    'transaction_lines.updated_at',
                ]),
        );

        DB::table('purchase_lines')->insertUsing(
            ['id', 'purchase_id', 'item_id', 'qty', 'unit_code', 'base_qty', 'unit_price', 'unit_cost', 'selling_price', 'total', 'cost', 'inner_units', 'created_at', 'updated_at'],
            DB::table('transaction_lines')
                ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
                ->where('transactions.type', 'purchase')
                ->select([
                    'transaction_lines.id',
                    'transaction_lines.transaction_id as purchase_id',
                    'transaction_lines.item_id',
                    'transaction_lines.qty',
                    'transaction_lines.unit_code',
                    'transaction_lines.base_qty',
                    'transaction_lines.unit_price',
                    'transaction_lines.unit_cost',
                    'transaction_lines.selling_price',
                    'transaction_lines.total',
                    'transaction_lines.cost',
                    'transaction_lines.inner_units',
                    'transaction_lines.created_at',
                    'transaction_lines.updated_at',
                ]),
        );
    }

    /**
     * Clearing the copied rows is enough to reverse this step; the legacy tables
     * still hold the original ledger, so nothing is lost.
     */
    public function down(): void
    {
        DB::table('sale_lines')->delete();
        DB::table('purchase_lines')->delete();
        DB::table('sales')->delete();
        DB::table('purchases')->delete();
    }
};
