<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds composite indexes that match the read paths the ledger actually runs.
 *
 * The single-column indexes created by the foreign keys already cover tenant
 * and branch lookups on their own; what is missing is the ordering and the
 * multi-column filters. The recent-activity and list pages read sales and
 * purchases newest-first by (transacted_on, id), the stock and report paths
 * scan stock_transfers by status and one of the two branch columns within a
 * date range, and the pack lookup takes the newest line per item. The existing
 * two-column date indexes are left in place: the new ones cover them as a
 * prefix, but keeping both is the safe choice and the write cost is small.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['tenant_id', 'branch_id', 'transacted_on', 'id']);
            $table->index(['tenant_id', 'transacted_on', 'id']);
            $table->index(['tenant_id', 'branch_id', 'paid', 'transacted_on']);
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index(['tenant_id', 'branch_id', 'transacted_on', 'id']);
            $table->index(['tenant_id', 'transacted_on', 'id']);
            $table->index(['tenant_id', 'stock_transfer_id', 'transacted_on']);
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            // Named by hand: the generated names would exceed MySQL's 64-char limit.
            $table->index(['tenant_id', 'status', 'source_branch_id', 'transferred_on'], 'stock_transfers_tenant_status_source_on_index');
            $table->index(['tenant_id', 'status', 'destination_branch_id', 'transferred_on'], 'stock_transfers_tenant_status_dest_on_index');
            $table->index(['tenant_id', 'transferred_on', 'id'], 'stock_transfers_tenant_transferred_id_index');
        });

        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->index(['item_id', 'id']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'is_active']);
        });

        Schema::table('purchase_lines', function (Blueprint $table) {
            $table->dropIndex(['item_id', 'id']);
        });

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropIndex('stock_transfers_tenant_status_source_on_index');
            $table->dropIndex('stock_transfers_tenant_status_dest_on_index');
            $table->dropIndex('stock_transfers_tenant_transferred_id_index');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'branch_id', 'transacted_on', 'id']);
            $table->dropIndex(['tenant_id', 'transacted_on', 'id']);
            $table->dropIndex(['tenant_id', 'stock_transfer_id', 'transacted_on']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'branch_id', 'transacted_on', 'id']);
            $table->dropIndex(['tenant_id', 'transacted_on', 'id']);
            $table->dropIndex(['tenant_id', 'branch_id', 'paid', 'transacted_on']);
        });
    }
};
