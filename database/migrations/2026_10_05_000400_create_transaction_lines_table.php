<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A line on a transaction.
 *
 * base_qty and unit_price are denormalised on write by LineNormalizer, so
 * stock and reports are plain SUM() aggregates that never need to join the
 * units table to know what a quantity meant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('qty', 18, 4);
            $table->string('unit_code', 32);
            $table->decimal('base_qty', 18, 4);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('total', 14, 2);
            $table->timestamps();

            $table->foreign('unit_code')->references('code')->on('units')
                ->restrictOnUpdate()->restrictOnDelete();

            $table->index(['item_id', 'unit_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_lines');
    }
};
