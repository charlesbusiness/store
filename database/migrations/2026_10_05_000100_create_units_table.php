<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Units are the measurement vocabulary. A unit belongs to exactly one family
 * (a dimension) and carries a multiplier relative to that family's base unit,
 * which is how stock can be stored in one comparable base quantity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('label', 64);
            $table->string('symbol', 32)->default('');
            $table->string('plural', 64)->nullable();
            $table->string('family', 16)->index();
            $table->decimal('base_multiplier', 18, 6)->default(1);
            $table->boolean('is_fraction')->default(false);
            $table->boolean('is_decimal')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['family', 'is_active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
