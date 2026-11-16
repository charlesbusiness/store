<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Splits the shared transactions/transaction_lines ledger into a sales pair and
 * a purchases pair.
 *
 * The two sides are mirror images but not identical: only a sale is marked paid,
 * and only a purchase can be the display record of an internal stock transfer.
 * Keeping them apart keeps each table's columns honest and lets each side carry
 * the tenant/branch indexes its own read paths actually use. The legacy tables
 * are left in place so the move can be rolled back; a later release drops them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('transacted_on');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('paid')->default(false);
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'transacted_on']);
            $table->index(['tenant_id', 'transacted_on']);
        });

        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 18, 4);
            $table->string('unit_code', 32);
            $table->decimal('base_qty', 18, 4);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->decimal('selling_price', 14, 2)->nullable();
            $table->decimal('total', 14, 2);
            $table->decimal('cost', 14, 2)->nullable();
            $table->json('inner_units')->nullable();
            $table->timestamps();

            $table->foreign('unit_code')->references('code')->on('units')
                ->restrictOnUpdate()->restrictOnDelete();

            $table->index(['sale_id', 'item_id']);
            $table->index(['item_id', 'unit_code']);
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('transacted_on');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_transfer_id')->nullable()->unique()->constrained('stock_transfers')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'transacted_on']);
            $table->index(['tenant_id', 'transacted_on']);
        });

        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 18, 4);
            $table->string('unit_code', 32);
            $table->decimal('base_qty', 18, 4);
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->decimal('selling_price', 14, 2)->nullable();
            $table->decimal('total', 14, 2);
            $table->decimal('cost', 14, 2)->nullable();
            $table->json('inner_units')->nullable();
            $table->timestamps();

            $table->foreign('unit_code')->references('code')->on('units')
                ->restrictOnUpdate()->restrictOnDelete();

            $table->index(['purchase_id', 'item_id']);
            $table->index(['item_id', 'unit_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_lines');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('sale_lines');
        Schema::dropIfExists('sales');
    }
};
