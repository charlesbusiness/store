<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One optional customer name for a sale.
 *
 * Kept separate from the transaction header so the sales record itself stays
 * focused on stock and money, while a sale still has one canonical customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->timestamps();

            $table->unique('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_names');
    }
};
